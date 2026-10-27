#!/usr/bin/env python3
# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
"""Pin the four FlexAccess plugins (and tool_mergeusers) to exact commits for CI (tool_flexaccess #12).

The four plugins are released together and depend on each other, so every CI run must test one exactly
known combination. This script turns a moving reference (a branch) into exact commit SHAs ONCE, checks
that every plugin carries the same family version, writes that combination as a lock (JSON) and fetches
exactly those commits for the jobs that test them. There is no fallback: a missing branch, an unknown
commit, a version that differs from the family version, a tampered lock or an unpinned tool_mergeusers
fail hard.

Commands
  resolve  Resolve the siblings of the plugin under test from a branch and write the lock.
           --wait SECONDS keeps polling while a sibling still carries another version (the four
           repositories can only be uploaded one after another); after the wait it fails.
  lock     Validate the versioned release lock (.github/flexaccess-family.lock.json) and complete it
           with the commit of the plugin under test ("SELF"). A sibling pinned by content (see below)
           is resolved to the commit on its branch that carries exactly that content.
  fetch    Fetch exactly the commits of a lock into a directory (moodle-plugin-ci extra plugins,
           a Moodle tree, or one directory per repository) and verify each fetched version.php.
  pin      Print a lock for the current heads of a branch (to commit as the release lock).
  pin-local
           Print a content-pinned release lock for four local repositories (the delivered release
           trees). It needs no commit SHA of the remote: the commit is found by its content.
  digest   Print the content digest of a commit of a local repository.

Content pins
  The four plugins are delivered as archives and uploaded to GitHub by hand, so the commit SHAs of a
  release only exist after the upload. A release lock can therefore pin a sibling by "content": the
  SHA-256 over the sorted (path, git blob SHA) pairs of every tracked file of the commit. It is
  independent of commit metadata and file modes, but changes with any added, removed or modified
  file. The gate accepts only the head of the sibling's branch with exactly that content.
  tool_flexaccess, which contains the lock, pins its own content without the lock file.
  zipcheck Build the distributed ZIP (git archive, honours export-ignore) of every pinned commit and
           compare it with the checksums of the lock, if the lock carries any.

Only the Python standard library and git are used. The configuration (repositories, Moodle paths,
pinned tool_mergeusers) is .github/flexaccess-family.json, identical in all four repositories.
"""

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import time

COMPONENTS = ['auth_flexaccess', 'enrol_flexaccess', 'mod_flexaccess', 'tool_flexaccess']
SHA_RE = re.compile(r'^[0-9a-f]{40}$')
DIGEST_RE = re.compile(r'^[0-9a-f]{64}$')
LOCK_PATH = '.github/flexaccess-family.lock.json'
HISTORY_DEPTH = 200
VERSION_RE = re.compile(r"\$plugin->version\s*=\s*([0-9]{10})\s*;")
COMPONENT_RE = re.compile(r"\$plugin->component\s*=\s*'([a-z0-9_]+)'\s*;")
HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_CONFIG = os.path.join(os.path.dirname(HERE), 'flexaccess-family.json')


class FamilyError(Exception):
    """A hard failure: the combination cannot be pinned or verified."""


# Options for every git call of the resolver. Since git 2.47, "git fetch" starts automatic maintenance (gc,
# commit-graph) as a detached background process. In the throw-away repositories of the resolver that process
# still writes into .git/objects while the directory is removed, and the removal fails ("Directory not empty:
# 'objects'", seen on the GitHub runner with git 2.55). The resolver never needs maintenance.
GIT_OPTIONS = ('-c', 'maintenance.auto=false', '-c', 'gc.auto=0', '-c', 'fetch.writeCommitGraph=false')


def git(*args, cwd=None):
    """Run git (without background maintenance) and return stdout; raise FamilyError on failure."""
    result = subprocess.run(['git', *GIT_OPTIONS, *args], cwd=cwd, capture_output=True, text=True)
    if result.returncode != 0:
        raise FamilyError('git %s failed: %s' % (' '.join(args), (result.stderr or result.stdout).strip()))
    return result.stdout


def load_config(path):
    """The family configuration: repositories, paths and the pinned tool_mergeusers."""
    with open(path, encoding='utf-8') as handle:
        config = json.load(handle)
    if sorted(config.get('plugins', {})) != sorted(COMPONENTS):
        raise FamilyError('The family configuration must list exactly %s.' % ', '.join(COMPONENTS))
    merge = config.get('mergeusers', {})
    if not SHA_RE.match(str(merge.get('sha', ''))):
        raise FamilyError('tool_mergeusers is not pinned to a commit SHA in %s.' % path)
    return config


def repo_url(repo):
    """Clone URL of a repository ("owner/name"); FLEXACCESS_FAMILY_URL lets tests use local repositories."""
    template = os.environ.get('FLEXACCESS_FAMILY_URL', 'https://github.com/{repo}.git')
    return template.format(repo=repo)


def plugin_facts(text):
    """Version and component from the text of a version.php."""
    version = VERSION_RE.search(text)
    component = COMPONENT_RE.search(text)
    return (int(version.group(1)) if version else None, component.group(1) if component else None)


def branch_sha(repo, ref):
    """The commit a branch points to right now; a missing branch is an error, never a fallback."""
    out = git('ls-remote', repo_url(repo), 'refs/heads/' + ref).split()
    if not out:
        raise FamilyError('Branch "%s" does not exist in %s (no fallback to another branch).' % (ref, repo))
    return out[0]


def fetch_commit(repo, sha, dest):
    """Fetch exactly one commit into dest (no history, no .git left behind)."""
    if not SHA_RE.match(sha):
        raise FamilyError('"%s" is not a full commit SHA (%s).' % (sha, repo))
    if os.path.exists(dest):
        shutil.rmtree(dest)
    os.makedirs(dest)
    git('init', '-q', cwd=dest)
    try:
        git('fetch', '-q', '--depth', '1', repo_url(repo), sha, cwd=dest)
    except FamilyError as error:
        raise FamilyError('Commit %s is not available in %s: %s' % (sha, repo, error)) from error
    git('-c', 'advice.detachedHead=false', 'checkout', '-q', 'FETCH_HEAD', cwd=dest)
    actual = git('rev-parse', 'HEAD', cwd=dest).strip()
    if actual != sha:
        raise FamilyError('Fetched %s instead of %s from %s.' % (actual, sha, repo))
    shutil.rmtree(os.path.join(dest, '.git'))


def remote_version(repo, sha):
    """Version and component of a commit, read without a working copy."""
    with tempfile.TemporaryDirectory(ignore_cleanup_errors=True) as tmp:
        git('init', '-q', cwd=tmp)
        git('fetch', '-q', '--depth', '1', repo_url(repo), sha, cwd=tmp)
        return plugin_facts(git('show', 'FETCH_HEAD:version.php', cwd=tmp))


def content_digest(cwd, commit='HEAD', exclude=()):
    """SHA-256 over the sorted "path NUL blob-SHA" lines of every tracked file of a commit."""
    listing = git('ls-tree', '-r', '-z', '--full-tree', commit, cwd=cwd)
    pairs = []
    for record in listing.split('\0'):
        if not record:
            continue
        meta, path = record.split('\t', 1)
        _mode, kind, blob = meta.split()
        if kind != 'blob' or path in exclude:
            continue
        pairs.append('%s\0%s\n' % (path, blob))
    return hashlib.sha256(''.join(sorted(pairs)).encode('utf-8')).hexdigest()


def find_content_commit(repo, ref, content, depth=HISTORY_DEPTH):
    """The head of a branch, provided it carries exactly the locked content (no drift, no leftovers)."""
    head = branch_sha(repo, ref)
    with tempfile.TemporaryDirectory(ignore_cleanup_errors=True) as tmp:
        git('init', '-q', cwd=tmp)
        git('fetch', '-q', '--depth', str(depth), repo_url(repo), 'refs/heads/%s:refs/remotes/lock/%s' % (ref, ref),
            cwd=tmp)
        if content_digest(tmp, head) == content:
            return head
        older = [sha for sha in git('rev-list', 'refs/remotes/lock/' + ref, cwd=tmp).split()[1:]
                 if content_digest(tmp, sha) == content]
    if older:
        raise FamilyError('The head of %s@%s no longer carries the locked content (it was at %s); the branch has '
                          'moved on. Create a new lock for the new state.' % (repo, ref, older[0]))
    raise FamilyError('The head of %s@%s does not carry the locked content %s. Upload exactly the released '
                      'repository (all files, nothing left over, deleted files removed), or create a new lock.'
                      % (repo, ref, content[:16]))


def read_version(directory):
    """Version and component of a plugin directory."""
    with open(os.path.join(directory, 'version.php'), encoding='utf-8') as handle:
        return plugin_facts(handle.read())


def validate_lock(lock, config, allow_self=None):
    """Structural and semantic checks of a lock; raises on any deviation (tamper detection)."""
    if lock.get('schema') != 1:
        raise FamilyError('Unknown lock schema %r.' % lock.get('schema'))
    family = lock.get('family_version')
    if not isinstance(family, int) or len(str(family)) != 10:
        raise FamilyError('The lock has no valid family_version.')
    plugins = lock.get('plugins', {})
    if sorted(plugins) != sorted(COMPONENTS):
        raise FamilyError('The lock must pin exactly %s.' % ', '.join(COMPONENTS))
    for component, entry in plugins.items():
        if entry.get('repo') != config['plugins'][component]['repo']:
            raise FamilyError('%s points to %r, expected %r.' % (component, entry.get('repo'),
                                                                  config['plugins'][component]['repo']))
        sha = entry.get('sha')
        if 'content' in entry and not DIGEST_RE.match(str(entry['content'])):
            raise FamilyError('%s has an invalid content digest (%r).' % (component, entry['content']))
        if sha == 'SELF' and component == allow_self:
            continue
        if sha is None and allow_self and 'content' in entry:
            # Pinned by content; the lock command resolves the commit.
            if entry.get('version') != family:
                raise FamilyError('%s is pinned at version %r, the family is %r.' % (
                    component, entry.get('version'), family))
            continue
        if not SHA_RE.match(str(sha)):
            raise FamilyError('%s is not pinned to a full commit SHA (%r).' % (component, sha))
        if entry.get('version') != family:
            raise FamilyError('%s is pinned at version %r, the family is %r.' % (component, entry.get('version'), family))
    merge = lock.get('mergeusers', {})
    if not SHA_RE.match(str(merge.get('sha', ''))):
        raise FamilyError('tool_mergeusers is not pinned to a commit SHA.')
    if merge.get('repo') != config['mergeusers']['repo']:
        raise FamilyError('tool_mergeusers points to %r, expected %r.' % (merge.get('repo'), config['mergeusers']['repo']))


def summary_markdown(lock, title):
    """The SHA matrix as a Markdown table for the job summary."""
    lines = ['### %s' % title, '', 'Family version `%s`' % lock['family_version'], '',
             '| Component | Repository | Commit | Version |', '|---|---|---|---|']
    for component in COMPONENTS:
        entry = lock['plugins'][component]
        lines.append('| %s | %s | `%s` | %s |' % (component, entry['repo'], entry['sha'], entry.get('version', '')))
    merge = lock['mergeusers']
    lines.append('| tool_mergeusers | %s | `%s` | %s |' % (merge['repo'], merge['sha'], merge.get('version', '')))
    return '\n'.join(lines) + '\n'


def emit(lock, args, title):
    """Write the lock to --out, the GitHub output and the job summary."""
    text = json.dumps(lock, indent=2, sort_keys=True)
    if args.out:
        with open(args.out, 'w', encoding='utf-8') as handle:
            handle.write(text + '\n')
    if args.github_output:
        with open(args.github_output, 'a', encoding='utf-8') as handle:
            handle.write('lock=%s\n' % json.dumps(lock, sort_keys=True))
    if args.summary:
        with open(args.summary, 'a', encoding='utf-8') as handle:
            handle.write(summary_markdown(lock, title))
    print(summary_markdown(lock, title))


def cmd_resolve(args, config):
    """Pin the siblings of the plugin under test from one branch, waiting for a rollout to finish."""
    own_version, own_component = read_version(args.self_dir)
    if own_component != args.self:
        raise FamilyError('%s/version.php declares %r, expected %r.' % (args.self_dir, own_component, args.self))
    deadline = time.time() + max(0, args.wait)
    while True:
        lock = {'schema': 1, 'family_version': own_version, 'source': 'branch:' + args.ref, 'plugins': {}}
        mismatches = []
        for component in COMPONENTS:
            repo = config['plugins'][component]['repo']
            if component == args.self:
                lock['plugins'][component] = {'repo': repo, 'sha': args.self_sha, 'version': own_version}
                continue
            sha = branch_sha(repo, args.ref)
            version, declared = remote_version(repo, sha)
            if declared != component:
                raise FamilyError('%s@%s declares component %r.' % (repo, sha, declared))
            lock['plugins'][component] = {'repo': repo, 'sha': sha, 'version': version}
            if version != own_version:
                mismatches.append('%s@%s (%s) is at version %s, %s is at %s' % (
                    component, args.ref, sha[:12], version, args.self, own_version))
        if not mismatches:
            break
        if time.time() >= deadline:
            raise FamilyError('The plugin family is not at one version:\n  ' + '\n  '.join(mismatches) +
                              '\nUpload all four plugins at the same version, then re-run the workflow.')
        print('Waiting for the rollout: ' + '; '.join(mismatches), flush=True)
        time.sleep(args.interval)
    merge = config['mergeusers']
    lock['mergeusers'] = {'repo': merge['repo'], 'sha': merge['sha'], 'version': merge.get('version')}
    validate_lock(lock, config)
    emit(lock, args, 'FlexAccess plugin family (resolved from %s)' % args.ref)


def cmd_lock(args, config):
    """Validate a committed release lock and complete it with the commit under test."""
    if not os.path.isfile(args.lock_file):
        raise FamilyError('There is no release lock %s yet. Run the release workflow with mode "propose" '
                          '(or "pin --ref development"), commit the printed lock and run the gate again.'
                          % args.lock_file)
    with open(args.lock_file, encoding='utf-8') as handle:
        lock = json.load(handle)
    validate_lock(lock, config, allow_self=args.self)
    own_version, own_component = read_version(args.self_dir)
    if own_component != args.self or own_version != lock['family_version']:
        raise FamilyError('%s is at %s (%s), the lock pins family version %s.' % (
            args.self_dir, own_version, own_component, lock['family_version']))
    if lock['plugins'][args.self]['sha'] != 'SELF':
        raise FamilyError('The lock must pin %s as "SELF" (the commit that contains the lock).' % args.self)
    own = lock['plugins'][args.self]
    if 'content' in own:
        actual = content_digest(args.self_dir, 'HEAD', exclude=(LOCK_PATH,))
        if actual != own['content']:
            raise FamilyError('%s: the commit under test carries content %s, the lock pins %s (the release '
                              'tree differs from the locked one).' % (args.self, actual[:16], own['content'][:16]))
    lock['plugins'][args.self] = dict(own, sha=args.self_sha, version=own_version)
    for component in COMPONENTS:
        if component == args.self:
            continue
        entry = lock['plugins'][component]
        if 'content' in entry:
            ref = entry.get('ref', 'development')
            if entry.get('sha') is None:
                deadline = time.time() + max(0, args.wait)
                while True:
                    try:
                        entry['sha'] = find_content_commit(entry['repo'], ref, entry['content'])
                        break
                    except FamilyError as error:
                        # The repositories are uploaded one after another: wait for the sibling.
                        if time.time() >= deadline:
                            raise
                        print('Waiting for %s: %s' % (component, error), flush=True)
                        time.sleep(args.interval)
            else:
                with tempfile.TemporaryDirectory(ignore_cleanup_errors=True) as tmp:
                    git('init', '-q', cwd=tmp)
                    git('fetch', '-q', '--depth', '1', repo_url(entry['repo']), entry['sha'], cwd=tmp)
                    if content_digest(tmp, 'FETCH_HEAD') != entry['content']:
                        raise FamilyError('%s@%s does not carry the locked content.' % (entry['repo'], entry['sha']))
        version, declared = remote_version(entry['repo'], entry['sha'])
        if declared != component or version != entry['version']:
            raise FamilyError('%s@%s is %s at version %s, the lock says %s.' % (
                entry['repo'], entry['sha'], declared, version, entry['version']))
    lock['source'] = 'lock:' + os.path.basename(args.lock_file)
    validate_lock(lock, config)
    emit(lock, args, 'FlexAccess plugin family (release lock)')


def lock_from_args(args, config):
    """The lock passed to fetch (JSON text or file)."""
    if args.lock_json:
        lock = json.loads(args.lock_json)
    else:
        with open(args.lock_file, encoding='utf-8') as handle:
            lock = json.load(handle)
    validate_lock(lock, config)
    return lock


def cmd_fetch(args, config):
    """Fetch the pinned commits and verify what was fetched."""
    lock = lock_from_args(args, config)
    os.makedirs(args.dest, exist_ok=True)
    for component in COMPONENTS:
        if component == args.self:
            continue
        entry = lock['plugins'][component]
        if args.layout == 'moodle':
            dest = os.path.join(args.dest, config['plugins'][component]['path'])
        else:
            dest = os.path.join(args.dest, entry['repo'].split('/')[-1])
        fetch_commit(entry['repo'], entry['sha'], dest)
        version, declared = read_version(dest)
        if declared != component or version != entry['version']:
            raise FamilyError('Fetched %s@%s is %s at %s, the lock says %s.' % (
                entry['repo'], entry['sha'], declared, version, entry['version']))
        print('%s %s -> %s' % (component, entry['sha'], dest))
    if args.with_mergeusers:
        merge = lock['mergeusers']
        if args.layout == 'moodle':
            dest = os.path.join(args.dest, config['mergeusers']['path'])
        else:
            dest = os.path.join(args.dest, merge['repo'].split('/')[-1])
        fetch_commit(merge['repo'], merge['sha'], dest)
        print('tool_mergeusers %s -> %s' % (merge['sha'], dest))


def cmd_pin(args, config):
    """A release lock for the current heads of a branch (tool_flexaccess itself as SELF)."""
    lock = {'schema': 1, 'plugins': {}}
    versions = set()
    for component in COMPONENTS:
        repo = config['plugins'][component]['repo']
        sha = branch_sha(repo, args.ref)
        version, declared = remote_version(repo, sha)
        if declared != component:
            raise FamilyError('%s@%s declares component %r.' % (repo, sha, declared))
        versions.add(version)
        lock['plugins'][component] = {'repo': repo, 'sha': 'SELF' if component == 'tool_flexaccess' else sha,
                                      'version': version}
        if component != 'tool_flexaccess':
            with tempfile.TemporaryDirectory(ignore_cleanup_errors=True) as tmp:
                git('init', '-q', cwd=tmp)
                git('fetch', '-q', '--depth', '1', repo_url(repo), sha, cwd=tmp)
                lock['plugins'][component]['content'] = content_digest(tmp, 'FETCH_HEAD')
    if len(versions) != 1:
        raise FamilyError('The heads of %s are not at one version: %s' % (args.ref, sorted(versions)))
    lock['family_version'] = versions.pop()
    merge = config['mergeusers']
    lock['mergeusers'] = {'repo': merge['repo'], 'sha': merge['sha'], 'version': merge.get('version')}
    print(json.dumps(lock, indent=2, sort_keys=True))


def cmd_pin_local(args, config):
    """A content-pinned release lock for four local release repositories (committed HEADs)."""
    lock = {'schema': 1, 'source': 'content', 'plugins': {}}
    versions = set()
    for component in COMPONENTS:
        repo = config['plugins'][component]['repo']
        directory = os.path.join(args.parent, repo.split('/')[-1])
        if git('status', '--porcelain', cwd=directory).strip():
            raise FamilyError('%s has uncommitted changes; lock only committed release trees.' % directory)
        version, declared = plugin_facts(git('show', 'HEAD:version.php', cwd=directory))
        if declared != component:
            raise FamilyError('%s declares component %r.' % (directory, declared))
        versions.add(version)
        entry = {'repo': repo, 'version': version, 'ref': args.ref}
        if component == 'tool_flexaccess':
            entry = {'repo': repo, 'version': version, 'sha': 'SELF',
                     'content': content_digest(directory, 'HEAD', exclude=(LOCK_PATH,))}
        else:
            entry['content'] = content_digest(directory, 'HEAD')
        lock['plugins'][component] = entry
    if len(versions) != 1:
        raise FamilyError('The local release trees are not at one version: %s' % sorted(versions))
    lock['family_version'] = versions.pop()
    merge = config['mergeusers']
    lock['mergeusers'] = {'repo': merge['repo'], 'sha': merge['sha'], 'version': merge.get('version')}
    validate_lock(lock, config, allow_self='tool_flexaccess')
    print(json.dumps(lock, indent=2, sort_keys=True))


def cmd_digest(args, config):
    """Content digest of a commit of a local repository."""
    exclude = (LOCK_PATH,) if args.without_lock else ()
    print(content_digest(args.dir, args.commit, exclude=exclude))


def cmd_zipcheck(args, config):
    """SHA-256 of the distributed archive (git archive) of every pinned commit; compare when pinned."""
    lock = lock_from_args(args, config)
    report = {}
    for component in COMPONENTS:
        entry = lock['plugins'][component]
        with tempfile.TemporaryDirectory(ignore_cleanup_errors=True) as tmp:
            git('init', '-q', cwd=tmp)
            git('fetch', '-q', '--depth', '1', repo_url(entry['repo']), entry['sha'], cwd=tmp)
            archive = os.path.join(tmp, 'release.zip')
            git('archive', '--format=zip', '--prefix=flexaccess/', '-o', archive, 'FETCH_HEAD', cwd=tmp)
            with open(archive, 'rb') as handle:
                digest = hashlib.sha256(handle.read()).hexdigest()
        report[component] = digest
        pinned = entry.get('zip_sha256')
        if pinned and pinned != digest:
            raise FamilyError('%s: release ZIP checksum %s does not match the lock (%s).' % (component, digest, pinned))
        print('%s %s %s' % (component, digest, 'matches lock' if pinned else '(not pinned)'))
    if args.out:
        with open(args.out, 'w', encoding='utf-8') as handle:
            json.dump(report, handle, indent=2, sort_keys=True)


def main(argv=None):
    """Command line entry point."""
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--config', default=DEFAULT_CONFIG)
    sub = parser.add_subparsers(dest='command', required=True)

    def outputs(p):
        p.add_argument('--out')
        p.add_argument('--github-output')
        p.add_argument('--summary')

    p = sub.add_parser('resolve')
    p.add_argument('--self', required=True, choices=COMPONENTS)
    p.add_argument('--self-dir', default='plugin')
    p.add_argument('--self-sha', required=True)
    p.add_argument('--ref', required=True)
    p.add_argument('--wait', type=int, default=0)
    p.add_argument('--interval', type=int, default=30)
    outputs(p)
    p = sub.add_parser('lock')
    p.add_argument('--lock-file', required=True)
    p.add_argument('--self', required=True, choices=COMPONENTS)
    p.add_argument('--self-dir', default='plugin')
    p.add_argument('--self-sha', required=True)
    p.add_argument('--wait', type=int, default=0, help='Seconds to wait for content-pinned siblings')
    p.add_argument('--interval', type=int, default=30)
    outputs(p)
    for name in ('fetch', 'zipcheck'):
        p = sub.add_parser(name)
        group = p.add_mutually_exclusive_group(required=True)
        group.add_argument('--lock-json')
        group.add_argument('--lock-file')
        if name == 'fetch':
            p.add_argument('--self', choices=COMPONENTS)
            p.add_argument('--dest', required=True)
            p.add_argument('--layout', choices=['extra', 'moodle'], default='extra')
            p.add_argument('--with-mergeusers', action='store_true')
        else:
            p.add_argument('--out')
    p = sub.add_parser('pin')
    p.add_argument('--ref', required=True)
    p = sub.add_parser('pin-local')
    p.add_argument('--parent', required=True, help='Directory holding the four repositories (moodle-<component>)')
    p.add_argument('--ref', default='development', help='Branch on which the siblings will be uploaded')
    p = sub.add_parser('digest')
    p.add_argument('--dir', required=True)
    p.add_argument('--commit', default='HEAD')
    p.add_argument('--without-lock', action='store_true', help='Exclude %s (tool_flexaccess)' % LOCK_PATH)

    args = parser.parse_args(argv)
    if getattr(args, 'self_sha', None) is not None and not SHA_RE.match(args.self_sha):
        parser.error('--self-sha must be a full commit SHA')
    try:
        config = load_config(args.config)
        {'resolve': cmd_resolve, 'lock': cmd_lock, 'fetch': cmd_fetch, 'pin': cmd_pin, 'pin-local': cmd_pin_local,
         'digest': cmd_digest, 'zipcheck': cmd_zipcheck}[args.command](args, config)
    except FamilyError as error:
        print('::error::FlexAccess plugin family: %s' % error, file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
