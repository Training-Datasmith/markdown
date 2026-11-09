#!/usr/bin/env python3
"""Apply lower snake_case to repo-owned methods, properties, variables, AST tags."""
from __future__ import annotations

import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

EXCLUDE_DIRS = {".git", "vendor", "__pycache__", ".system-test"}
EXCLUDE_FILES = {
    "scripts/rename-old-names.txt",
    "CHANGELOG.md",
    "scripts/apply-snake-rename.py",
    "scripts/fix_lower_snake.py",
}

# External / PHP / PHPUnit names that must not change when they appear as whole identifiers.
KEEP_IDENT = {
    "ReflectionClass", "ReflectionMethod", "TestCase", "FilesystemIterator",
    "RecursiveDirectoryIterator", "RecursiveIteratorIterator", "SplFileInfo",
    "getName", "getMethods", "getDocComment", "assertEquals", "assertSame",
    "assertStringEndsWith", "GitHub", "LaTeX", "HTML", "PHP", "URL", "PHPDoc",
    "IS_PROTECTED", "ENT_HTML401", "ENT_COMPAT", "ENT_NOQUOTES", "ENT_SUBSTITUTE",
    "Markdown", "Parser", "Github", "Extra", "My", "Ol", "Hr", "Ul", "Code", "Html",
    "Base", "Test", "Trait", "Data", "Paths", "Files", "Framework", "PHPUnit",
    "XHPROF", "XHProfRuns", "Default", "XHProf", "Runs",
}


def is_studly_snake_class(name: str) -> bool:
    return bool(re.match(r"^[A-Z][a-zA-Z0-9]*(?:_[A-Z][a-zA-Z0-9]*)+$", name))


def to_lower_snake(name: str) -> str:
    if name in KEEP_IDENT or is_studly_snake_class(name):
        return name
    if "_" in name:
        return "_".join(part.lower() for part in name.split("_"))
    s = re.sub(r"([a-z0-9])([A-Z])", r"\1_\2", name)
    s = re.sub(r"([A-Z]+)([A-Z][a-z])", r"\1_\2", s)
    return s.lower()


def collect_identifiers(text: str) -> set[str]:
    ids: set[str] = set()
    for m in re.finditer(r"\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(", text):
        ids.add(m.group(1))
    for m in re.finditer(r"\$([a-zA-Z_][a-zA-Z0-9_]*)", text):
        ids.add("$" + m.group(1))
    for m in re.finditer(r"'([a-z][a-zA-Z0-9_]*)'", text):
        ids.add(m.group(1))
    for m in re.finditer(r'\b([a-z][a-zA-Z0-9_]*)\s+as\s+(?:public|protected|private)', text):
        ids.add(m.group(1))
    return ids


def build_renames(paths: list[str]) -> dict[str, str]:
    mapping: dict[str, str] = {}
    for path in paths:
        text = open(path, encoding="utf-8", errors="replace").read()
        for ident in collect_identifiers(text):
            bare = ident[1:] if ident.startswith("$") else ident
            if bare in KEEP_IDENT:
                continue
            if is_studly_snake_class(bare):
                continue
            if not re.search(r"[A-Z]", bare) and "_" not in bare:
                continue
            if "_" in bare and bare == bare.lower():
                continue
            new = to_lower_snake(bare)
            if new != bare:
                key = ident if ident.startswith("$") else bare
                mapping[key] = ("$" + new if ident.startswith("$") else new)
    # force known leftovers
    extras = {
        "testParse": "test_parse",
        "testUtf8": "test_utf8",
        "composerAutoload": "composer_autoload",
        "renderFencedCode": "render_code",
        "fencedCode": "code",
        "special_Attributes": "special_attributes",
        "inline_Code": "inline_code",
        "auto_Url": "auto_url",
        "table_Boundary": "table_boundary",
        "inline_Html": "inline_html",
    }
    for a, b in extras.items():
        if to_lower_snake(a) == b or a != b:
            mapping[a] = b
    return mapping


def apply_mapping(text: str, mapping: dict[str, str]) -> str:
    # Longest keys first
    for old in sorted(mapping.keys(), key=len, reverse=True):
        new = mapping[old]
        if old.startswith("$"):
            text = re.sub(r"\$" + re.escape(old[1:]) + r"\b", "$" + new[1:], text)
        else:
            text = re.sub(r"\b" + re.escape(old) + r"\b", new, text)
    return text


def iter_paths() -> list[str]:
    paths: list[str] = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in EXCLUDE_DIRS]
        for fn in filenames:
            rel = os.path.relpath(os.path.join(dirpath, fn), ROOT)
            if rel in EXCLUDE_FILES:
                continue
            if rel.startswith("tests/") and rel.endswith((".md", ".html")):
                continue
            if fn.endswith((".php", ".md", ".xml", ".dist", ".json")) or fn == "markdown":
                paths.append(os.path.join(dirpath, fn))
    return paths


def main() -> None:
    paths = iter_paths()
    mapping = build_renames(paths)
    for path in paths:
        text = open(path, encoding="utf-8", errors="replace").read()
        new = apply_mapping(text, mapping)
        if new != text:
            with open(path, "w", encoding="utf-8") as f:
                f.write(new)
    print("renamed", len(mapping), "identifiers")


if __name__ == "__main__":
    main()
