#!/usr/bin/env python3
"""Copies the live espadna.com sites into a folder for the `mirror` branch.

The Iranian host (mirror-ir/sync.php) cannot download large files from
Cloudflare (Iranian links stall after ~63 KB), but GitHub works, so the
mirror-pack workflow keeps an up-to-date copy on raw.githubusercontent.com.

    python3 tools/mirror_pack.py <folder>

Each site goes to <folder>/<name>/ with its files.json (sha256 of every file
and the _redirects text). Only changed files are downloaded; each is checked
against its sha256. A site whose source is unreachable keeps its old copy.
"""
import hashlib
import json
import pathlib
import shutil
import sys
import urllib.parse
import urllib.request

FILE_SITES = {
    'espadna': 'https://espadna.com',
    'pantomime': 'https://pantomime.espadna.com',
    'tiaro': 'https://tiaro.espadna.com',
    'tiaro-app': 'https://espadna.com/tiaro-app',
    'adadi': 'https://adadi.espadna.com',
    'adadi-app': 'https://espadna.com/adadi-app',
}
API = 'https://api.espadna.com'
API_JSON = {'config.json': 'config_version', 'words_fa.json': 'version'}


def get(url: str) -> bytes:
    # Accept: */* (not text/html): Cloudflare then serves the HTML as
    # published, without injecting its analytics script.
    req = urllib.request.Request(url, headers={'User-Agent': 'espadna-mirror-pack/1',
                                               'Accept': '*/*',
                                               'Cache-Control': 'no-cache'})
    with urllib.request.urlopen(req, timeout=120) as r:
        return r.read()


def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def pack_site(out: pathlib.Path, name: str, src: str) -> str:
    try:
        manifest = json.loads(get(src + '/files.json'))
        files = manifest['files']
    except Exception as e:  # unreachable, or no files.json (SPA answers HTML)
        return f'{name}: skipped ({type(e).__name__})'
    folder = out / name
    staging = out / f'.{name}.new'
    shutil.rmtree(staging, ignore_errors=True)
    fetched = 0
    for f in files:
        rel = f['path']
        if rel.startswith('/') or '..' in rel.split('/'):
            continue
        old = folder / rel
        if old.is_file() and sha(old.read_bytes()) == f['sha256']:
            data = old.read_bytes()
        else:
            data = get(src + '/' + urllib.parse.quote(rel))
            if sha(data) != f['sha256']:
                shutil.rmtree(staging, ignore_errors=True)
                return f'{name}: {rel} changed while copying, keeping the old copy'
            fetched += 1
        target = staging / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
    (staging / 'files.json').write_text(json.dumps(manifest))
    shutil.rmtree(folder, ignore_errors=True)
    staging.rename(folder)
    return f'{name}: {manifest.get("version")} ({len(files)} files, {fetched} downloaded)'


def pack_api(out: pathlib.Path) -> str:
    folder = out / 'api'
    folder.mkdir(parents=True, exist_ok=True)
    done = []
    for file, key in API_JSON.items():
        try:
            raw = get(f'{API}/{file}')
            if not isinstance(json.loads(raw).get(key), int):
                raise ValueError('no version')
        except Exception as e:
            done.append(f'{file} skipped ({type(e).__name__})')
            continue
        (folder / file).write_bytes(raw)
        done.append(file)
    try:
        page = get(f'{API}/privacy')
        if b'<html' in page.lower():
            (folder / 'privacy').write_bytes(page)
            done.append('privacy')
    except Exception as e:
        done.append(f'privacy skipped ({type(e).__name__})')
    return 'api: ' + ', '.join(done)


def write_sites(out: pathlib.Path) -> str:
    """sites.json: every mirrored site, so mirror-ir/sync.php picks up new apps by
    itself (no config.php edit on the host), plus the current sync.php with its
    sha256 so the host script can update itself."""
    sites = []
    for name, src in FILE_SITES.items():
        if name == 'espadna':
            continue  # the studio site: always in config.php (it is the root folder)
        url = urllib.parse.urlsplit(src)
        host, path = url.netloc, url.path.strip('/')
        if path:  # espadna.com/<path>: a folder of espadna.ir
            sites.append({'name': name, 'kind': 'folder', 'path': path})
        else:     # <sub>.espadna.com: a subdomain of espadna.ir (a web game: SPA)
            sites.append({'name': name, 'kind': 'subdomain', 'sub': host.split('.')[0], 'spa': True})
    script = pathlib.Path(__file__).resolve().parent.parent / 'mirror-ir' / 'sync.php'
    data = script.read_bytes()
    (out / 'sync.php').write_bytes(data)
    (out / 'sites.json').write_text(json.dumps({'sites': sites, 'sync_php': {'sha256': sha(data)}}, indent=1))
    return f'sites.json: {len(sites)} sites, sync.php {sha(data)[:12]}'


def main() -> None:
    out = pathlib.Path(sys.argv[1])
    out.mkdir(parents=True, exist_ok=True)
    for name, src in FILE_SITES.items():
        print(pack_site(out, name, src))
    print(pack_api(out))
    print(write_sites(out))


if __name__ == '__main__':
    main()
