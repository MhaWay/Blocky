#!/usr/bin/env python3
"""Build the single-plugin wp.org bundle: ggally-page-builder.

Assembles engine (core-plugin) + builder (builder-plugin) under one plugin
folder with the main loader, correct permissions (644/755 — WP skips
unreadable main files silently via @include_once!), and a proper zip.
"""
import os, re, shutil, subprocess, sys, zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PKG = os.path.join(ROOT, 'packages')
OUT_ROOT = '/tmp/ggally-bundle'
OUT = os.path.join(OUT_ROOT, 'ggally-page-builder')
HERE = os.path.join(ROOT, 'scripts', 'wporg')

def strip_entry(src, dst):
    s = open(src, encoding='utf-8').read()
    s = re.sub(r'/\*\*.*?\*/', '/** Bootstrap (loaded by ggally-page-builder.php). */', s, count=1, flags=re.S)
    open(dst, 'w', encoding='utf-8').write(s)

def main():
    shutil.rmtree(OUT_ROOT, ignore_errors=True)
    os.makedirs(OUT + '/engine', exist_ok=True)
    os.makedirs(OUT + '/builder', exist_ok=True)

    src = os.path.join(PKG, 'core-plugin')
    DEV_FILES = {'composer.json', 'composer.lock', 'package.json', 'phpcs.xml', 'phpcs.xml.dist', 'phpunit.xml'}
    for m in os.listdir(src):
        if m.startswith('.'):
            continue
        if m in ('readme.txt', 'blocky-core.php', 'node_modules', 'tests'):
            continue
        s, d = os.path.join(src, m), os.path.join(OUT, 'engine', m)
        (shutil.copytree if os.path.isdir(s) else shutil.copy2)(s, d)
    strip_entry(os.path.join(src, 'blocky-core.php'), OUT + '/engine/engine.php')
    # Production-only PHP deps (drops phpstan/phpunit phars).
    subprocess.run(['composer', 'install', '--no-dev', '--quiet', '--no-interaction',
                   '--working-dir', OUT + '/engine'], check=True)

    src = os.path.join(PKG, 'builder-plugin')
    DEV_FILES = {'composer.json', 'composer.lock', 'package.json', 'phpcs.xml', 'phpcs.xml.dist', 'phpunit.xml'}
    for m in os.listdir(src):
        if m.startswith('.'):
            continue
        if m in ('readme.txt', 'blocky-builder.php', 'node_modules', 'tests', 'scripts'):
            continue
        s, d = os.path.join(src, m), os.path.join(OUT, 'builder', m)
        (shutil.copytree if os.path.isdir(s) else shutil.copy2)(s, d)
    strip_entry(os.path.join(src, 'blocky-builder.php'), OUT + '/builder/builder.php')

    # Text domain must equal slug for the directory (Plugin Check ERROR otherwise).
    import re as _re
    pat = _re.compile(r"(,\s*)'blocky'(\s*\))")
    for root, dirs, files in os.walk(OUT):
        for f in files:
            if not f.endswith('.php'):
                continue
            fp = os.path.join(root, f)
            c = open(fp, encoding='utf-8').read()
            c = pat.sub(lambda m: m.group(1) + "'ggally-page-builder'" + m.group(2), c)
            c = chr(10).join(((l[:len(l) - len(l.lstrip())] + "\\load_plugin_textdomain('ggally-page-builder', false, \\dirname(\\plugin_basename(\\dirname(__DIR__) . '/ggally-page-builder.php')) . '/languages');") if l.strip().lstrip(chr(92)).startswith('load_plugin_textdomain(') else l) for l in c.split(chr(10)))
            open(fp, 'w', encoding='utf-8').write(c)
    # WordPress.org auto-loads translations for a slug text domain from <plugin>/languages/*.mo.
    top = os.path.join(OUT, 'languages')
    os.makedirs(top, exist_ok=True)
    for sub in ('engine', 'builder'):
        d = os.path.join(OUT, sub, 'languages')
        if os.path.isdir(d):
            for f in os.listdir(d):
                if f.startswith('blocky-') and f.endswith('.mo'):
                    dst = os.path.join(top, 'ggally-page-builder-' + f[7:])
                    if not os.path.exists(dst):
                        shutil.copy(os.path.join(d, f), dst)
            shutil.rmtree(d)

    os.makedirs(OUT + '/engine/assets/css', exist_ok=True)
    shutil.copy('/root/state/blocky/packages/builder-plugin/src/styles/generated/tailwind-utility-safelist.css', OUT + '/engine/assets/css/vocabulary-fallback.css')

    shutil.copy(os.path.join(HERE, 'ggally-page-builder.php'), OUT + '/ggally-page-builder.php')
    shutil.copy(os.path.join(HERE, 'readme.txt'), OUT + '/readme.txt')

    for root, dirs, files in os.walk(OUT):
        os.chmod(root, 0o755)
        for f in files:
            os.chmod(os.path.join(root, f), 0o644)

    zip_path = '/tmp/ggally-page-builder.zip'
    zf = zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED)
    for root, dirs, files in os.walk(OUT):
        arc = os.path.relpath(root, OUT_ROOT)
        for f in sorted(files):
            if f.endswith(('.po', '.pot')) or f == '.buildinfo':
                continue
            zi = zipfile.ZipInfo(arc + '/' + f, (1980, 1, 1, 0, 0, 0))
            zi.external_attr = (0o100644 << 16)
            zi.compress_type = zipfile.ZIP_DEFLATED
            zf.writestr(zi, open(os.path.join(root, f), 'rb').read(), compresslevel=9)
    zf.close()
    print('built', zip_path, os.path.getsize(zip_path) // 1024, 'KB')

if __name__ == '__main__':
    main()
