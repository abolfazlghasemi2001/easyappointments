#!/usr/bin/env python3
"""
Mini "composer install" replacement for environments where packagist.org is not
reachable but api.github.com / codeload.github.com are.

It reads composer.lock, downloads every package zipball into vendor/<name>,
and generates a PSR-4 / PSR-0 / classmap / files autoloader at vendor/autoload.php.

This script is a development helper only. On a normal machine (or inside the
provided Docker stack) use "composer install" instead.

Usage: python3 dev/sandbox/minicomposer.py <project-root>
"""
from __future__ import annotations

import io
import json
import os
import re
import shutil
import sys
import urllib.request
import zipfile

UA = {"User-Agent": "minicomposer/1.0"}


def fetch(url: str, timeout: int = 120) -> bytes:
    last_exc = None
    # api.github.com/.../zipball/<sha> redirects to codeload.github.com. If a
    # redirect target is blocked we retry with the explicit codeload URL.
    candidates = [url]
    m = re.match(r"https://api\.github\.com/repos/([^/]+)/([^/]+)/zipball/([0-9a-f]+)", url)
    if m:
        owner, repo, sha = m.groups()
        candidates.append(f"https://codeload.github.com/{owner}/{repo}/legacy.zip/{sha}")
    for candidate in candidates:
        try:
            req = urllib.request.Request(candidate, headers=UA)
            with urllib.request.urlopen(req, timeout=timeout) as response:
                return response.read()
        except Exception as exc:  # noqa: BLE001
            last_exc = exc
    raise RuntimeError(f"download failed for {url}: {last_exc}")


def extract(data: bytes, destination: str) -> None:
    with zipfile.ZipFile(io.BytesIO(data)) as zf:
        names = zf.namelist()
        if not names:
            raise RuntimeError("empty archive")
        root = names[0].split("/")[0] + "/"
        for info in zf.infolist():
            if not info.filename.startswith(root) or info.filename.endswith("/"):
                continue
            relative = info.filename[len(root):]
            target = os.path.join(destination, relative)
            os.makedirs(os.path.dirname(target), exist_ok=True)
            with zf.open(info) as src, open(target, "wb") as dst:
                shutil.copyfileobj(src, dst)


def php_dump(value, level: int = 0) -> str:
    """Renders a Python value as a PHP literal (arrays, strings, numbers)."""
    indent = "    " * level
    indent_inner = "    " * (level + 1)
    if isinstance(value, dict):
        if not value:
            return "[]"
        items = [
            f"{indent_inner}{php_dump(key)} => {php_dump(item, level + 1)},"
            for key, item in value.items()
        ]
        return "[\n" + "\n".join(items) + f"\n{indent}]"
    if isinstance(value, (list, tuple)):
        if not value:
            return "[]"
        items = [f"{indent_inner}{php_dump(item, level + 1)}," for item in value]
        return "[\n" + "\n".join(items) + f"\n{indent}]"
    if isinstance(value, bool):
        return "true" if value else "false"
    if isinstance(value, (int, float)):
        return str(value)
    if value is None:
        return "null"
    return "'" + str(value).replace("\\", "\\\\").replace("'", "\\'") + "'"


def php_files(directory: str):
    for base, _dirs, files in os.walk(directory):
        for name in files:
            if name.endswith(".php"):
                yield os.path.join(base, name)


def classmap_for(directory: str) -> dict:
    """Very small class/interface/trait/enum scanner (good enough for vendor code)."""
    found = {}
    pattern = re.compile(
        r"^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)",
        re.M,
    )
    namespace_pattern = re.compile(r"^\s*namespace\s+([^;{]+)", re.M)
    for path in php_files(directory):
        try:
            with open(path, "r", encoding="utf-8", errors="ignore") as handle:
                source = handle.read()
        except OSError:
            continue
        if "class" not in source and "interface" not in source and "trait" not in source and "enum" not in source:
            continue
        namespace_match = namespace_pattern.search(source)
        namespace = namespace_match.group(1).strip() if namespace_match else ""
        for name in pattern.findall(source):
            fqcn = f"{namespace}\\{name}" if namespace else name
            found.setdefault(fqcn, path)
    return found


def main() -> int:
    root = os.path.abspath(sys.argv[1] if len(sys.argv) > 1 else ".")
    lock_path = os.path.join(root, "composer.lock")
    vendor = os.path.join(root, "vendor")

    with open(lock_path, encoding="utf-8") as handle:
        lock = json.load(handle)

    packages = lock.get("packages", []) + lock.get("packages-dev", [])

    psr4: dict[str, list[str]] = {}
    psr0: dict[str, list[str]] = {}
    classmap: dict[str, str] = {}
    files: list[str] = []
    include_paths: list[str] = []

    for package in packages:
        name = package["name"]
        destination = os.path.join(vendor, name)
        if not os.path.isdir(destination) or not os.listdir(destination):
            url = package.get("dist", {}).get("url")
            if not url:
                print(f"!! {name}: no dist url, skipped")
                continue
            print(f"-> {name} {package.get('version')}")
            try:
                data = fetch(url)
            except Exception as exc:  # noqa: BLE001
                print(f"!! {name}: {exc}")
                continue
            os.makedirs(destination, exist_ok=True)
            extract(data, destination)
        else:
            print(f"== {name}: already present")

        composer_json = os.path.join(destination, "composer.json")
        if os.path.isfile(composer_json):
            with open(composer_json, encoding="utf-8") as handle:
                meta = json.load(handle)
        else:
            meta = {}

        autoload = meta.get("autoload", {}) or {}

        for prefix, paths in (autoload.get("psr-4", {}) or {}).items():
            for path in [paths] if isinstance(paths, str) else paths:
                psr4.setdefault(prefix, []).append(os.path.join(destination, path))

        for prefix, paths in (autoload.get("psr-0", {}) or {}).items():
            for path in [paths] if isinstance(paths, str) else paths:
                psr0.setdefault(prefix, []).append(os.path.join(destination, path))

        for path in autoload.get("classmap", []) or []:
            target = os.path.join(destination, path)
            if os.path.isfile(target):
                classmap.update(classmap_for(os.path.dirname(target)))
                classmap[os.path.basename(target)] = target
            elif os.path.isdir(target):
                classmap.update(classmap_for(target))

        for path in autoload.get("files", []) or []:
            files.append(os.path.join(destination, path))

        for path in meta.get("include-path", []) or []:
            include_paths.append(os.path.join(destination, path))

    # Project level autoload (psr-4) so that tests/ classes are autoloadable.
    with open(os.path.join(root, "composer.json"), encoding="utf-8") as handle:
        project = json.load(handle)
    for prefix, paths in (project.get("autoload", {}).get("psr-4", {}) or {}).items():
        for path in [paths] if isinstance(paths, str) else paths:
            psr4.setdefault(prefix, []).append(os.path.join(root, path))

    autoload_body = f"""<?php
// Generated by dev/sandbox/minicomposer.py - minimal Composer autoloader.
$__psr4 = {php_dump(dict(sorted(psr4.items())))};
$__psr0 = {php_dump(dict(sorted(psr0.items())))};
$__classmap = {php_dump(dict(sorted(classmap.items())))};
$__files = {php_dump(files)};
$__include = {php_dump(include_paths)};

if ($__include) {{
    set_include_path(implode(PATH_SEPARATOR, $__include) . PATH_SEPARATOR . get_include_path());
}}

spl_autoload_register(static function ($class) use ($__psr4, $__psr0, $__classmap) {{
    if (isset($__classmap[$class])) {{
        require_once $__classmap[$class];
        return;
    }}

    foreach ($__psr4 as $prefix => $directories) {{
        if ($prefix !== '' && strncmp($class, $prefix, strlen($prefix)) !== 0) {{
            continue;
        }}
        $relative = ltrim(substr($class, strlen($prefix)), '\\\\');
        $file = str_replace('\\\\', '/', $relative) . '.php';
        foreach ($directories as $directory) {{
            $candidate = rtrim($directory, '/') . '/' . $file;
            if (is_file($candidate)) {{
                require_once $candidate;
                return;
            }}
        }}
    }}

    foreach ($__psr0 as $prefix => $directories) {{
        if ($prefix !== '' && strncmp($class, $prefix, strlen($prefix)) !== 0) {{
            continue;
        }}
        $file = str_replace('\\\\', '/', $class) . '.php';
        foreach ($directories as $directory) {{
            $candidate = rtrim($directory, '/') . '/' . $file;
            if (is_file($candidate)) {{
                require_once $candidate;
                return;
            }}
        }}
    }}
}});

foreach ($__files as $__file) {{
    if (is_file($__file)) {{
        require_once $__file;
    }}
}}

return true;
"""
    os.makedirs(vendor, exist_ok=True)
    with open(os.path.join(vendor, "autoload.php"), "w", encoding="utf-8") as handle:
        handle.write(autoload_body)

    print(f"\nDone. packages={len(packages)} psr4={len(psr4)} classmap={len(classmap)} files={len(files)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
