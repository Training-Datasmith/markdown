#!/usr/bin/env python3
"""One-shot snake_case rename for cebe/markdown (repo-owned names only)."""
from __future__ import annotations

import os
import re
import shutil

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Longest-first identifier renames (old -> new). External names excluded.
IDENT_RENAMES = [
    ("MarkdownOLStartNumTest", "Markdown_OL_Start_Num_Test"),
    ("GithubMarkdownTest", "Github_Markdown_Test"),
    ("MarkdownExtraTest", "Markdown_Extra_Test"),
    ("BaseMarkdownTest", "Base_Markdown_Test"),
    ("EmphStrongTrait", "Emph_Strong_Trait"),
    ("FencedCodeTrait", "Fenced_Code_Trait"),
    ("StrikeoutTrait", "Strikeout_Trait"),
    ("UrlLinkTrait", "Url_Link_Trait"),
    ("HeadlineTrait", "Headline_Trait"),
    ("GithubMarkdown", "Github_Markdown"),
    ("MarkdownExtra", "Markdown_Extra"),
    ("MarkdownTest", "Markdown_Test"),
    ("ParserTest", "Parser_Test"),
    ("TestParser", "Test_Parser"),
    ("CodeTrait", "Code_Trait"),
    ("HtmlTrait", "Html_Trait"),
    ("LinkTrait", "Link_Trait"),
    ("ListTrait", "List_Trait"),
    ("QuoteTrait", "Quote_Trait"),
    ("RuleTrait", "Rule_Trait"),
    ("TableTrait", "Table_Trait"),
    ("testAutoLinkLabelingWithEncodedUrl", "test_Auto_Link_Labeling_With_Encoded_Url"),
    ("testMaxNestingLevel", "test_Max_Nesting_Level"),
    ("testKeepZeroAlive", "test_Keep_Zero_Alive"),
    ("testMarkerOrder", "test_Marker_Order"),
    ("testInvalidUtf8", "test_Invalid_Utf8"),
    ("testPregReplaceR", "test_Preg_Replace_R"),
    ("testEdgeCases", "test_Edge_Cases"),
    ("testNewlines", "test_Newlines"),
    ("maximumNestingLevel", "maximum_nesting_level"),
    ("keepListStartNumber", "keep_List_Start_Number"),
    ("codeAttributesOnPre", "code_Attributes_On_Pre"),
    ("enableNewlines", "enable_Newlines"),
    ("escapeCharacters", "escape_Characters"),
    ("outputFileExtension", "output_File_Extension"),
    ("inlineHtmlElements", "inline_Html_Elements"),
    ("selfClosingHtmlElements", "self_Closing_Html_Elements"),
    ("_specialAttributesRegex", "_special_Attributes_Regex"),
    ("_inlineMarkers", "_inline_Markers"),
    ("_blockTypes", "_block_types"),
    ("createMarkdown", "create_Markdown"),
    ("getDataPaths", "get_Data_Paths"),
    ("getTestData", "get_Test_Data"),
    ("dataFiles", "data_Files"),
    ("parseMarkerA", "parse_marker_a"),
    ("parseMarkerB", "parse_marker_b"),
    ("parseMarkerC", "parse_marker_c"),
    ("identifyReference", "identify_Reference"),
    ("consumeReference", "consume_Reference"),
    ("consumeFencedCode", "consume_Fenced_Code"),
    ("renderAttributes", "render_Attributes"),
    ("parseSpecialAttributes", "parse_Special_Attributes"),
    ("renderSpecialAttributes", "render_Special_Attributes"),
    ("identifyFencedCode", "identify_Fenced_Code"),
    ("identifyHeadline", "identify_Headline"),
    ("consumeHeadline", "consume_Headline"),
    ("renderHeadline", "render_Headline"),
    ("identifyHtml", "identify_Html"),
    ("consumeHtml", "consume_Html"),
    ("renderHtml", "render_Html"),
    ("parseEntity", "parse_Entity"),
    ("renderInlineHtml", "render_Inline_Html"),
    ("parseInlineHtml", "parse_Inline_Html"),
    ("identifyOl", "identify_Ol"),
    ("identifyUl", "identify_Ul"),
    ("consumeOl", "consume_Ol"),
    ("consumeUl", "consume_Ul"),
    ("consumeList", "consume_List"),
    ("renderList", "render_List"),
    ("generateHtmlAttributes", "generate_Html_Attributes"),
    ("identifyQuote", "identify_Quote"),
    ("consumeQuote", "consume_Quote"),
    ("renderQuote", "render_Quote"),
    ("identifyHr", "identify_Hr"),
    ("consumeHr", "consume_Hr"),
    ("renderHr", "render_Hr"),
    ("identifyTable", "identify_Table"),
    ("consumeTable", "consume_Table"),
    ("renderTable", "render_Table"),
    ("composeTable", "compose_Table"),
    ("parseTd", "parse_Td"),
    ("identifyCode", "identify_Code"),
    ("consumeCode", "consume_Code"),
    ("renderCode", "render_Code"),
    ("parseInlineCode", "parse_Inline_Code"),
    ("renderInlineCode", "render_Inline_Code"),
    ("parseEmphStrong", "parse_Emph_Strong"),
    ("renderStrong", "render_Strong"),
    ("renderEmph", "render_Emph"),
    ("replaceEscape", "replace_Escape"),
    ("parseLinkOrImage", "parse_Link_Or_Image"),
    ("parseInline", "parse_Inline"),
    ("parseEscape", "parse_Escape"),
    ("parseStrike", "parse_Strike"),
    ("renderStrike", "render_Strike"),
    ("parseUrl", "parse_Url"),
    ("renderAutoUrl", "render_Auto_Url"),
    ("lookupReference", "lookup_Reference"),
    ("renderEmail", "render_Email"),
    ("renderImage", "render_Image"),
    ("blockTypes", "block_Types"),
    ("detectLineType", "detect_Line_Type"),
    ("parseBlocks", "parse_Blocks"),
    ("parseBlock", "parse_Block"),
    ("renderAbsy", "render_Absy"),
    ("consumeParagraph", "consume_Paragraph"),
    ("renderParagraph", "render_Paragraph"),
    ("inlineMarkers", "inline_Markers"),
    ("prepareMarkers", "prepare_Markers"),
    ("parseParagraph", "parse_Paragraph"),
    ("renderText", "render_Text"),
    ("renderLink", "render_Link"),
    ("renderUrl", "render_Url"),
    ("parseLink", "parse_Link"),
    ("parseImage", "parse_Image"),
    ("parseLt", "parse_Lt"),
    ("parseGt", "parse_Gt"),
    ("identifyAHr", "identify_A_Hr"),
    ("identifyBUl", "identify_B_Ul"),
    ("consumeAHr", "consume_A_Hr"),
    ("consumeBUl", "consume_B_Ul"),
    ("pregData", "preg_Data"),
    ("utfNaturalUrl", "utf_Natural_Url"),
    ("utfEncodedUrl", "utf_Encoded_Url"),
    ("eucEncodedUrl", "euc_Encoded_Url"),
    ("utfText", "utf_Text"),
    ("terminatingMarkerPos", "terminating_Marker_Pos"),
    ("specialAttributes", "special_Attributes"),
    ("inlineCode", "inline_Code"),
    ("inlineHtml", "inline_Html"),
    ("tableBoundary", "table_Boundary"),
    ("autoUrl", "auto_Url"),
    ("lazyItems", "lazy_Items"),
    ("lastLineEmpty", "last_Line_Empty"),
    ("normalizedKey", "normalized_Key"),
    ("secureUrlText", "secure_Url_Text"),
    ("refMatches", "ref_Matches"),
    ("textMatches", "text_Matches"),
    ("itemId", "item_Id"),
    ("itemLines", "item_Lines"),
    ("nextLine", "next_Line"),
    ("firstPar", "first_Par"),
    ("leadSpace", "lead_Space"),
    ("attrName", "attr_Name"),
    ("attrValue", "attr_Value"),
    ("cellTag", "cell_Tag"),
    ("gfm_parser", "gfm_parser"),  # already snake
    ("extra_parser", "extra_parser"),
    ("inline_parser", "inline_parser"),
]

FILE_RENAMES = [
    ("GithubMarkdown.php", "Github_Markdown.php"),
    ("MarkdownExtra.php", "Markdown_Extra.php"),
    ("block/CodeTrait.php", "block/Code_Trait.php"),
    ("block/FencedCodeTrait.php", "block/Fenced_Code_Trait.php"),
    ("block/HeadlineTrait.php", "block/Headline_Trait.php"),
    ("block/HtmlTrait.php", "block/Html_Trait.php"),
    ("block/ListTrait.php", "block/List_Trait.php"),
    ("block/QuoteTrait.php", "block/Quote_Trait.php"),
    ("block/RuleTrait.php", "block/Rule_Trait.php"),
    ("block/TableTrait.php", "block/Table_Trait.php"),
    ("inline/CodeTrait.php", "inline/Code_Trait.php"),
    ("inline/EmphStrongTrait.php", "inline/Emph_Strong_Trait.php"),
    ("inline/LinkTrait.php", "inline/Link_Trait.php"),
    ("inline/StrikeoutTrait.php", "inline/Strikeout_Trait.php"),
    ("inline/UrlLinkTrait.php", "inline/Url_Link_Trait.php"),
    ("tests/BaseMarkdownTest.php", "tests/Base_Markdown_Test.php"),
    ("tests/GithubMarkdownTest.php", "tests/Github_Markdown_Test.php"),
    ("tests/MarkdownExtraTest.php", "tests/Markdown_Extra_Test.php"),
    ("tests/MarkdownOLStartNumTest.php", "tests/Markdown_OL_Start_Num_Test.php"),
    ("tests/MarkdownTest.php", "tests/Markdown_Test.php"),
    ("tests/ParserTest.php", "tests/Parser_Test.php"),
]

TEXT_GLOBS = [
    "Parser.php",
    "Markdown.php",
    "Github_Markdown.php",
    "Markdown_Extra.php",
    "block",
    "inline",
    "tests",
    "bin/markdown",
    "examples",
    "phpunit.xml.dist",
    "README.md",
    "ARCHITECTURE.md",
    "composer.json",
]


def walk_text_files() -> list[str]:
    paths: list[str] = []
    for item in TEXT_GLOBS:
        path = os.path.join(ROOT, item)
        if os.path.isfile(path):
            paths.append(path)
        elif os.path.isdir(path):
            for dirpath, _, files in os.walk(path):
                for f in files:
                    if f.endswith((".php", ".md", ".xml", ".dist", ".json", "")) or f == "markdown":
                        paths.append(os.path.join(dirpath, f))
    return sorted(set(paths))


def apply_ident_renames(text: str) -> str:
    for old, new in IDENT_RENAMES:
        if old == new:
            continue
        text = re.sub(r"\b" + re.escape(old) + r"\b", new, text)
    # Dynamic dispatch prefixes
    text = text.replace("strncmp($name, 'identify', 8)", "strncmp($name, 'identify_', 9)")
    text = text.replace("substr($name, 8)", "substr($name, 9)")
    text = text.replace("'identify' . $blockType", "'identify_' . $blockType")
    text = text.replace("'consume' . $blockType", "'consume_' . $blockType")
    text = text.replace("'render' . $block[0]", "'render_' . $block[0]")
    text = text.replace("'consume' . $blockType", "'consume_' . $blockType")
    text = text.replace("in_array('parseLink'", "in_array('parse_Link'")
    text = text.replace("method_exists($this, 'parseInlineHtml')", "method_exists($this, 'parse_Inline_Html')")
    text = text.replace("method_exists($this, 'identifyHr')", "method_exists($this, 'identify_Hr')")
    text = text.replace("method_exists($this, 'identifyReference')", "method_exists($this, 'identify_Reference')")
    text = text.replace("'parseInlineHtml'", "'parse_Inline_Html'")
    text = text.replace("'identifyHr'", "'identify_Hr'")
    text = text.replace("'identifyReference'", "'identify_Reference'")
    return text


def strip_strict_types_library(text: str, path: str) -> str:
    rel = os.path.relpath(path, ROOT)
    if rel.startswith("tests/"):
        return text
    if rel in {
        "Parser.php",
        "Markdown.php",
        "Github_Markdown.php",
        "Markdown_Extra.php",
        "bin/markdown",
        "examples/basic_rendering.php",
    } or rel.startswith("block/") or rel.startswith("inline/"):
        return re.sub(r"^\s*declare\(strict_types=1\);\n\n?", "", text, count=1, flags=re.M)
    return text


def main() -> None:
    paths = walk_text_files()
    for path in paths:
        if "vendor" in path or ".git" in path:
            continue
        with open(path, encoding="utf-8", errors="replace") as f:
            text = f.read()
        new = apply_ident_renames(text)
        new = strip_strict_types_library(new, path)
        if new != text:
            with open(path, "w", encoding="utf-8") as f:
                f.write(new)

    for old, new in FILE_RENAMES:
        src = os.path.join(ROOT, old)
        dst = os.path.join(ROOT, new)
        if os.path.isfile(src):
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            if os.path.exists(dst):
                os.remove(dst)
            shutil.move(src, dst)

    print("rename applied")


if __name__ == "__main__":
    main()
