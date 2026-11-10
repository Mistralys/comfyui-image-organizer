---
name: flux-prompt-generation
description: Convert an image prompt (SDXL-style keyword lists, negative prompts, rough ideas) into a FLUX.1 Dev compatible natural-language prompt. Use when the user asks to convert, rewrite, adapt or generate a prompt for FLUX.
---

# FLUX Prompt Generation

Converts the user's image prompt into a prompt that suits FLUX.1 Dev in a local ComfyUI workflow. The rules come from [docs/flux-prompting-guide.md](../../../docs/flux-prompting-guide.md); read it when a rule below needs more detail (lighting, camera and style vocabulary, negative-prompt replacements).

## Input

The user's prompt, which may include a negative prompt, weight syntax, quality tags, LoRA trigger words, or only a rough idea. If a negative prompt is not given, none is assumed.

## Procedure

1. **Extract the intent.** Identify the subject, action, setting, style, mood and any text that must appear. Do not add subjects, objects or story elements the user did not imply. Note any element whose size, count or placement is not stated.
2. **Preserve literal tokens.** Keep LoRA trigger words and any LoRA tags exactly as written, and place trigger words where they read naturally. Keep quoted text and proper names verbatim.
3. **Remove what a FLUX prose prompt does not need.**
   - Quality tags (`masterpiece`, `best quality`, `8k`, `highly detailed`, `ultrarealistic`).
   - Weight syntax such as `(word:1.3)`. ComfyUI does parse it for FLUX.1, but convert it to prose emphasis (place the element earlier and describe it in more detail) unless the user asks to keep weights.
   - The negative prompt. See step 4.
   - Escape any literal parentheses that remain as `\(` and `\)`, because ComfyUI reads unescaped `(text)` as emphasis.
4. **Convert negatives to positives.** For each negative term, decide what should be seen instead and describe that (`no people` becomes `empty`, `no text` becomes `clean surfaces`). Drop terms that are generic quality noise (`lowres`, `blurry`, `extra fingers`, `watermark`) and list them in the notes.
5. **Rewrite as prose in this order**, putting first whatever matters most:
   1. Image type and medium
   2. Subject and what it is doing
   3. Setting
   4. Light: source, direction, quality, color
   5. Framing and detail: shot size, angle, lens, depth of field
   6. Style, palette and one or two effects
6. **Add concrete detail only where the input implies it.** Prefer what a real camera would record (`visible grain`, `a warm color cast`) over generic quality words. Name a camera, lens or film stock for photographic looks. Do not invent detail that changes the user's concept.
7. **Keep it proportionate, but give each requested element its own sentence.** A short input gets a short prompt of a few sentences. Do not pad. Remove any sentence that does not describe something visible. When the input lists several required elements, describe each in a sentence of its own, with what it physically is, how many there are and where they are. An element left as a clause in a list can be dropped.
8. **Match the setting words to the look.** Use `natural`, `irregular`, `raw rock` and similar words for a natural setting, and avoid `symmetrical`, `circular` or `arched` unless a built look is wanted. Describe the feature that should be there (a solid rock ceiling) instead of leaving it unmentioned.
9. **Text in the image.** For each text block, quote the exact words, say where they go and describe the type (weight, case, color).

## Output

Return exactly two parts:

1. The FLUX prompt in a single fenced `text` block, as one paragraph with no negative prompt and no parameter syntax.
2. A short **Notes** list (a few bullets) covering what was changed and why: removed tags or weights, negatives converted or dropped, details added, and any assumption made. Flag anything ambiguous as a question rather than guessing, including the scale of an important object when it is not stated.

## Rules

- FLUX in this project does not use negative prompts. Never output a negative prompt section.
- Do not output generation settings (CFG, steps, sampler, resolution) unless the user asks.
- Do not claim behavior that is not in the guide. If the user asks about a FLUX feature the guide does not cover, say so.
