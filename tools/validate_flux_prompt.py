#!/usr/bin/env python3
"""Check FLUX.1 Dev prompt length against the prompting guide's recommendations."""

import argparse
import re
import sys
from pathlib import Path


def keep_longest(variants, text):
    """Keep the longest word count for each possible whitespace boundary."""
    boundary = (text[0].isspace(), text[-1].isspace()) if text else None
    if boundary not in variants or len(text.split()) > len(variants[boundary].split()):
        variants[boundary] = text


def combine(left, right):
    """Combine expansions without losing choices that affect adjacent words."""
    result = {}
    for prefix in left.values():
        for suffix in right.values():
            keep_longest(result, prefix + suffix)
    return result


def longest_expansion(prompt):
    """Return one maximum-word frontend expansion and counts of groups and comments."""
    current = {None: ""}
    alternatives = {}
    stack = []
    literal = []
    groups = 0
    comments = 0
    index = 0

    while index < len(prompt):
        char = prompt[index]
        if char == "\\" and index + 1 < len(prompt) and prompt[index + 1] in "{}|":
            literal.append(prompt[index + 1])
            index += 2
            continue
        if prompt.startswith("//", index):
            comments += 1
            end = prompt.find("\n", index + 2)
            index = len(prompt) if end == -1 else end
            continue
        if prompt.startswith("/*", index):
            end = prompt.find("*/", index + 2)
            if end == -1:
                raise ValueError("unclosed block comment")
            comments += 1
            index = end + 2
            continue
        if char not in "{}" and not (char == "|" and stack):
            literal.append(char)
            index += 1
            continue

        if literal:
            current = combine(current, {None: "".join(literal)})
            literal.clear()
        if char == "{":
            stack.append((current, alternatives))
            current, alternatives = {None: ""}, {}
            groups += 1
        elif char == "|":
            for text in current.values():
                keep_longest(alternatives, text)
            current = {None: ""}
        else:
            if not stack:
                raise ValueError("unmatched closing brace; escape literal braces with a backslash")
            for text in current.values():
                keep_longest(alternatives, text)
            prefix, parent_alternatives = stack.pop()
            current = combine(prefix, alternatives)
            alternatives = parent_alternatives
        index += 1

    if literal:
        current = combine(current, {None: "".join(literal)})
    if stack:
        raise ValueError("unclosed option group; escape literal braces with a backslash")
    return max(current.values(), key=lambda text: len(text.split())).strip(), groups, comments


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Check a FLUX.1 Dev prompt's approximate length, not its token count."
    )
    source = parser.add_mutually_exclusive_group()
    source.add_argument("--text", help="prompt text")
    source.add_argument("--file", type=Path, help="UTF-8 text file containing the prompt")
    args = parser.parse_args()

    try:
        if args.text is not None:
            prompt = args.text
        elif args.file is not None:
            prompt = args.file.read_text(encoding="utf-8-sig")
        else:
            if sys.stdin.isatty():
                parser.error("provide --text, --file, or a prompt on standard input")
            prompt = sys.stdin.buffer.read().decode("utf-8-sig")
    except (OSError, UnicodeError) as error:
        parser.exit(2, f"Error reading prompt: {error}\n")

    prompt = prompt.strip()
    if not prompt:
        parser.exit(2, "Error: prompt is empty.\n")

    try:
        prompt, groups, comments = longest_expansion(prompt)
    except ValueError as error:
        parser.exit(2, f"Error in dynamic prompt: {error}.\n")
    if not prompt:
        parser.exit(2, "Error: prompt is empty after dynamic prompt processing.\n")

    words = len(prompt.split())
    paragraphs = len(re.split(r"\n\s*\n", prompt))
    sentences = len(
        [part for part in re.split(r"""(?<=[.!?])["')\]]*(?=\s|$)""", prompt) if part.strip()]
    )

    print(f"Words: {words} (whitespace-separated)")
    print(f"Sentences: approximately {sentences}")
    print(f"Paragraphs: {paragraphs}")
    if groups or comments:
        print(
            "Counts use one maximum-word expansion with frontend comments removed; "
            "other choices can be shorter. Direct API submissions do not expand options."
        )
    print("Default: one paragraph, 3-6 sentences and roughly 40-120 words.")
    if words < 40 or sentences < 3:
        print("Note: shorter prompts are suitable for exploration or iteration.")
    if words > 120 or sentences > 6 or paragraphs > 1:
        print("Note: longer prompts can be appropriate for complex scenes.")
    if words >= 300:
        print("Warning: the guide recommends staying below roughly 300 words.")
    if words >= 350:
        print("Warning: 350-400 English words roughly correspond to 512 T5 tokens.")
    print(
        "CLIP-L uses the first 77-token chunk (roughly 50-60 English words); "
        "T5's reference limit is 512 tokens."
    )
    print("Word and sentence counts are estimates; actual token counts are not measured.")
    return 1 if words >= 300 else 0


if __name__ == "__main__":
    sys.exit(main())
