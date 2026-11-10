# FLUX Prompting Guide

How to write image prompts for FLUX.1 Dev in a local ComfyUI workflow, such as the FLUX workflows this project organizes (see [workflows/readme.md](workflows/readme.md)).

To convert an existing prompt automatically, use the `flux-prompt-generation` skill in `.github/skills/flux-prompt-generation/SKILL.md`.

## Scope and sources

This guide is a synthesis of two sources:

- [PrompTessor: FLUX Prompt Generator](https://promptessor.com/flux-prompt-generator): a short technique list, a mistakes list and one worked example.
- [Black Forest Labs (BFL) FLUX prompting guide](https://docs.bfl.ai/guides/prompting_summary): the official guide that the PrompTessor page cites as its source.

The BFL guide is written for its hosted FLUX 3 Image model. The parts that describe general prompt writing (structure, lighting, camera terms, replacing negative prompts) are included here. Hosted-API features are left out: JSON and layout (bounding box) prompts, `aspect_ratio`/`resolution` request fields and multi-reference editing.

The guidance below has not been tested against FLUX.1 Dev specifically. Treat it as a strong starting point and compare results in your own workflow.

## How FLUX prompts differ from SDXL prompts

| SDXL habit | FLUX approach |
|---|---|
| A pile of comma-separated keywords | A clear description with a subject, a setting and a camera and lighting relationship |
| A negative prompt | No negative prompt. Describe what you want to see instead (see [Working without negative prompts](#working-without-negative-prompts)) |
| Quality tags such as "masterpiece", "best quality" | Leave them out. Describe what a real camera would record |
| Weight syntax such as `(word:1.3)` | ComfyUI parses it for FLUX.1, but prefer emphasis through placement and detail (see [Weight syntax in ComfyUI](#weight-syntax-in-comfyui)) |

In this project's FLUX workflows, negative prompts are not used and CFG is virtually always `1.0` (see [workflows/readme.md](workflows/readme.md)).

## Prompt structure

A clear structure matters more than length. A useful order is:

1. **Image type and medium:** one opening sentence that sets the look. Example: `High-angle black and white photograph with a grainy film texture`.
2. **Subject:** who or what, and what they are doing. Example: `Two red pandas resting on bamboo beams, one relaxed and the other asleep`.
3. **Setting:** where it happens and what surrounds the subject.
4. **Light:** source, direction, quality and color.
5. **Framing and detail:** shot size, angle, lens, depth of field and the small details that make the image specific.

The order is a habit, not a rule: put first whatever matters most. If the frame is the point of the image (an ultra-wide shot, a macro), open with it.

Put the main subject and action early, then add environment, spatial relationships and composition.

### Length

Start with one or two sentences and add detail as you review results. Longer prompts help when there are several subjects, a specific layout or text in the image. Add only details that describe something visible, and remove any sentence that does not.

Strong prompts are specific, not necessarily long. The component list below is not a checklist to fill in every time.

## Prompt components

| Component | What it controls | Example phrases |
|---|---|---|
| Image type | Overall category and framing | `portrait`, `landscape`, `macro`, `bird's-eye view` |
| Subject | The main focus | `an elderly man with a long white beard`, `a single red rose` |
| Location | Setting and mood | `inside a dimly lit jazz club`, `in a dense forest after rain` |
| Style | Visual language | `fashion editorial photography`, `oil painting`, `anime illustration` |
| Camera | Lens, framing, depth of field | `85mm lens`, `shallow depth of field`, `low-angle shot` |
| Lighting | Contrast, mood, depth | `soft window light`, `golden hour sunlight`, `harsh direct flash` |
| Colors | Palette | `muted beige and forest green tones`, `monochrome black and white` |
| Effects | Visual treatment | `film grain`, `soft bloom`, `motion blur` |
| Additional elements | Supporting details | `wind-blown fabric`, `glowing reflections on wet pavement` |

Use one or two strong effects. Too many make the image unfocused.

### Subject and relationships

Be specific when it matters: `a young woman with curly red hair` renders more consistently than `a woman`. When there are several subjects, describe how they relate in full sentences: who faces whom, who is in focus, who is asleep.

The points below come from iterating on a single scene (see [Worked example: iterating on a scene](#worked-example-iterating-on-a-scene)). They have not been tested beyond that scene.

- **Give every must-have element its own sentence.** An element mentioned only as a short clause in a list can be dropped entirely. In the cavern example, statues and crystals listed as clauses did not appear; a detailed sentence for each made them appear.
- **Say what the thing physically is, and state count and placement.** A role word such as `guardians` can be misread (in the example, as faces hanging from the ceiling). `One life-size carved stone statue of a human woman in each alcove, standing on a plinth, hands folded` is unambiguous.
- **State relative scale when it matters.** If size is not given, the model chooses it. Use a reference: `about the height of the statues`, `twice as tall as a person`.
- **Describe a new object part by part** (for a tree: trunk, branches, leaves, and what each is made of). This keeps it from blending into similar elements nearby, such as crystals on a wall.

### Style

Name the style concretely and mention it early when it is central to the result. "The grainy texture of mid-20th-century color film" says more than "vintage".

| Category | Keywords |
|---|---|
| Photographic | `shot on Kodak Portra 400`, `35mm film`, `Sony A7IV`, `Hasselblad X2D` |
| Cinematic | `cinematic`, `anamorphic lens flare`, `teal and orange color grading`, `film noir` |
| Artistic | `oil painting`, `watercolor`, `pencil sketch`, `impasto texture` |
| Digital art | `concept art`, `matte painting`, `stylized 3D` |
| Illustration | `flat design`, `vector illustration`, `comic art`, `graphic novel` |
| Vintage | `80s vintage photo`, `2000s digicam`, `polaroid`, `sepia tone` |

For photographs, name the camera, lens or film stock instead of writing "professional photo", then describe what that look does to the image: `visible grain`, `a warm color cast`, `slight chromatic aberration at the edges`.

### Natural versus built settings

Material and origin words steer a setting toward looking grown or constructed. In the cavern example, `circular`, `symmetrical` and `arched` produced a built hall with smooth walls and a skylight. `Vast natural cavern`, `irregular`, `raw rock`, `craggy`, `hewn into the rock` and `chisel marks` produced a natural cave with carved alcoves.

| For a natural look | For a built look |
|---|---|
| `natural cavern`, `irregular rock walls`, `uneven vaulted ceiling` | `hall`, `chamber with smooth plastered walls` |
| `raw rock`, `craggy`, `weathered` | `masonry`, `polished`, `cut stone blocks` |
| `hewn into the rock`, `chisel marks` | `built`, `architectural`, `symmetrical`, `ordered rows of arches` |

### Camera and lens

| Term | Effect |
|---|---|
| `f/1.4` to `f/2.8` | Blurry background (shallow depth of field) |
| `f/8` to `f/16` | Everything sharp (deep depth of field) |
| `24mm` | Wide angle, shows more of the scene |
| `35mm` | Natural, documentary-style perspective |
| `50mm` | Eye-level, neutral perspective |
| `85mm` | Portrait-ideal, slight background compression |
| `135mm` and longer | Telephoto, strong background compression |
| `macro lens` | Extreme close-up detail |
| `anamorphic lens` | Widescreen cinematic look with oval bokeh |

Example: `Shot on Hasselblad X2D, 80mm lens, f/2.8, natural lighting`.

If a result keeps pulling back further than you want, state the subject and its framing first and move environmental details later.

The viewpoint also decides what is visible. In the cavern example, a view from the center of the room hid the alcoves around the edge; `wide shot from a low angle near the entrance, looking across the chamber` showed them.

### Lighting

Name the source, direction and quality, and say what the light does. `soft window light from the left` describes more than `good lighting`.

| Term | Effect |
|---|---|
| `golden hour` | Warm, soft, flattering |
| `blue hour` | Cool, moody |
| `overcast` | Flat, even, shadow-free; suits product shots |
| `Rembrandt lighting` | Dramatic triangle of light on the face |
| `split lighting` | High contrast, half the face lit |
| `chiaroscuro` | Strong light and shadow drama |
| `backlit` / `rim light` | Subject glowing at the edges |
| `soft box` / `key light` | Studio, controlled, even |
| `practical lighting` | Visible light sources in the scene (lamps, neon, fire) |
| `harsh direct light` | Strong shadows, high contrast |

### Composition

| Technique | Example phrase |
|---|---|
| Rule of thirds | `composed using rule of thirds` |
| Leading lines | `diagonal lines leading to the main entrance` |
| Foreground and background layers | `strong foreground boulder, background mountains` |
| Low angle | `low angle worm's eye view` |
| High angle | `bird's eye view, geometric patterns of city blocks` |
| Dutch angle | `dutch angle, psychological tension` |
| Symmetry | `perfectly symmetrical composition` |
| Negative space | `minimalist composition with generous negative space` |

### Colors

Lighting already influences color, but explicit palette direction keeps the image coherent: `warm orange and pink sunset colors`, `desaturated pastel palette`, `a futuristic busy city, purple and green color scheme`.

Use exact hex codes only when precise color matters (a brand color, a specific object) and tie each code to one named object: `the logo text "ACME" in color #FF5733`. Hex-code behavior is documented by BFL for its hosted models; check it in your own FLUX.1 Dev workflow before relying on it.

## Weight syntax in ComfyUI

In a vanilla ComfyUI FLUX.1 workflow, `(word)` and `(word:1.3)` are parsed by ComfyUI itself, not by the model. The FLUX.1 tokenizer applies the same weight parser to both of its text encoders (CLIP-L and T5-XXL), and the weights scale the encoded tokens toward or away from the empty-prompt embedding. This was verified in the ComfyUI source (`comfy/sd1_clip.py`, `comfy/text_encoders/flux.py` and `comfy/text_encoders/sd3_clip.py`), not by testing images.

| Syntax | Effect |
|---|---|
| `(word)` | Weight 1.1 |
| `(word:1.3)` | Weight 1.3 |
| `((word))` | Weights multiply: 1.1 x 1.1 |
| `\(` and `\)` | Literal parentheses |

Points to know:

- **Literal parentheses are consumed.** Any `(text)` in a prompt is read as emphasis and the brackets are removed. Escape real parentheses as `\(` and `\)`.
- **How strong the effect is has not been verified.** The source shows that weights are applied, not how visibly they change a FLUX image. Compare with and without them in your own workflow.
- **Pooled CLIP-L output is not weighted.** Only the token embeddings are scaled.
- **Not portable.** Other hosts (hosted APIs, other UIs) may not parse it, and ComfyUI's newer FLUX.2 text encoders disable weight parsing. This applies to the vanilla `CLIPTextEncode` and `CLIPTextEncodeFlux` nodes; a custom prompt node may behave differently.

For FLUX prose prompts, describing the important element earlier and in more detail is the approach the BFL guide uses, so this guide and the `flux-prompt-generation` skill convert weights to prose by default.

## Working without negative prompts

When you catch yourself writing a negative phrase:

1. Identify the unwanted element ("no crowds").
2. Ask what would fill that space instead.
3. Describe that ("an empty promenade", "a single figure on the pier").

| Instead of | Write |
|---|---|
| `no people` | `empty`, `deserted`, `solitary` |
| `no colors` | `monochrome`, `black and white`, `grayscale` |
| `no text` | `clean surfaces`, `unmarked`, `blank` |
| `no background clutter` | `plain studio backdrop in one color` |
| `no modern elements` | `traditional`, `historical`, `period-accurate` |
| `not dark` | `brightly lit`, `sun-drenched` |
| `not sad` | `joyful`, `content` |

If an unwanted element keeps appearing:

- Be more specific about what you want in that space.
- Put the replacement early, in the sentence that sets the scene, not in a trailing clause.
- Add detail to the replacement (material, color, what it is doing) so it takes up the space.
- Give it context so it belongs in the scene: `a deserted beach at dawn, before the first swimmers arrive`.

Features you do not mention can be invented. In the cavern example the model added a sky opening in the ceiling; describing the intended feature (`a solid rock ceiling hung with stalactites`) replaced it.

## Text in images

For each block of text, state three things:

1. **The exact words**, in quotation marks, with the capitalization and punctuation you want.
2. **Where it goes** in the frame.
3. **The type:** weight, case, color and character.

Example: `a paper poster on the wall that reads "OPEN LATE", in bold white sans-serif capitals`.

Tips:

- Front-load the text description and keep strings short. Long strings are harder to render accurately.
- Mark line breaks and direction in the description (`arranged in two centered lines`).
- Proofread every quoted string in the output.

## Common mistakes

- A long keyword pile with no clear subject, scene hierarchy, camera and lighting relationship.
- Copying parameter syntax from another image platform without checking that your FLUX host supports it.
- Quality tags ("masterpiece", "highly detailed", "ultrarealistic") used in place of concrete description.
- Negative phrasing ("no hat", "not blurry") instead of a positive description.
- Stacking many effects, or mixing styles that do not belong together.
- Listing important elements as short clauses, so they are dropped (see [Subject and relationships](#subject-and-relationships)).
- Role or abstract words (`guardians`, `peaceful`) in place of a physical description of what the thing is.
- Words such as `symmetrical`, `circular` or `arched` in a scene meant to look natural (see [Natural versus built settings](#natural-versus-built-settings)).

## Worked example

Illustrative conversion of an SDXL-style prompt:

**Before (SDXL-style)**

```text
masterpiece, best quality, (beautiful woman:1.3), red hair, forest, sunset, bokeh, 8k, highly detailed
Negative: blurry, lowres, extra fingers, text, watermark
```

**After (FLUX)**

```text
Portrait photograph of a woman with long red hair standing among tall pine trees at sunset. Warm golden hour light falls on her face from the left, and the forest behind her dissolves into soft bokeh. Shot on an 85mm lens at f/1.8, shallow depth of field, sharp skin texture, subtle film grain, warm amber and deep green tones.
```

What changed:

- Quality tags and `8k` were dropped; camera and film detail describe the look instead.
- The weight syntax was removed; the subject is stated first in plain language.
- The negative terms were dropped. "Blurry" is handled by sharp-subject wording and "extra fingers" has no direct positive equivalent here.
- Keywords were turned into sentences in the order image type, subject, setting, light, framing.

## Worked example: iterating on a scene

A fantasy cavern prompt, revised over four generations. The images were judged by eye, with no controlled comparison.

**First prompt (abridged)**

```text
Fantasy digital concept art of a circular room in a crystal cavern. At the center stands a stone pedestal with a large crystal egg. Crystals grow out of the walls and glow from within. Peaceful female stone guardians stand in alcoves around the walls. Pastel pink and purple palette.
```

Result: the alcoves, egg and pedestal appeared. The guardians and crystals did not, and faces hung from the ceiling.

**Changes and effects**

1. The guardians and crystals each got a detailed sentence (life-size stone statues of human women, one per alcove, robed, hands folded; large faceted crystal clusters between the alcoves). The ceiling was described as solid rock. Statues and crystals appeared, and the faces went away.
2. The wording changed from `circular` and `symmetrical` to `vast natural cavern`, `raw rock`, `hewn into the rock` and `chisel marks`, and the viewpoint moved to a low angle near the entrance. The result looked like a natural cave with the alcoves visible.
3. The egg sentence was replaced by a translucent crystal tree described part by part (trunk, branches, large leaves). The rest of the prompt was left unchanged.

## Iterating on a prompt

- Change one element at a time and keep the rest of a working prompt as it is.
- When an element is missing or misread, rewrite its description before adding more words elsewhere.
- When an unwanted feature appears, describe what should be there instead.

## Further reading

- [ComfyUI source: `comfy/sd1_clip.py`](https://github.com/comfyanonymous/ComfyUI/blob/master/comfy/sd1_clip.py) (weight parsing and encoding)
- [ComfyUI source: `comfy/text_encoders/flux.py`](https://github.com/comfyanonymous/ComfyUI/blob/master/comfy/text_encoders/flux.py) (FLUX tokenizers)
- [BFL: FLUX Prompting Guide](https://docs.bfl.ai/guides/prompting_summary)
- [BFL: Building a Good Prompt](https://docs.bfl.ai/guides/prompting_unified_building)
- [BFL: Style, Aesthetics & Text](https://docs.bfl.ai/guides/prompting_unified_style)
- [BFL: Technical Parameters](https://docs.bfl.ai/guides/prompting_unified_technical)
- [BFL: Prompt Reference](https://docs.bfl.ai/guides/prompting_unified_reference)
- [PrompTessor: FLUX Prompt Generator](https://promptessor.com/flux-prompt-generator)
