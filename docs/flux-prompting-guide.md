# FLUX Prompting Guide

How to write image prompts for FLUX.1 Dev in a local ComfyUI workflow, such as the FLUX workflows this project organizes (see [workflows/readme.md](workflows/readme.md)).

This guide covers FLUX.1 Dev only. FLUX.2 and FLUX 3 use different text encoders and are prompted differently.

To convert an existing prompt automatically, use the `flux-prompt-generation` skill in `.github/skills/flux-prompt-generation/SKILL.md`.

## Scope and sources

Guidance in this guide has one of four bases. Sections name theirs in a `Basis:` line.

| Basis | Meaning |
|---|---|
| Verified in source | Read in the ComfyUI or Black Forest Labs (BFL) source code. Not tested on images |
| Observed here | Seen while iterating on a scene in this project, judged by eye (see [Worked example: iterating on a scene](#worked-example-iterating-on-a-scene)) |
| Community practice | Widely reported for FLUX.1 Dev. Not tested in this project |
| FLUX 3 guide | BFL advice written for a newer model and assumed to carry over |

The written sources are:

- [PrompTessor: FLUX Prompt Generator](https://promptessor.com/flux-prompt-generator): a short technique list, a mistakes list and one worked example.
- [BFL FLUX prompting guide](https://docs.bfl.ai/guides/prompting_summary): the official guide that the PrompTessor page cites as its source.

The BFL guide is written for the hosted FLUX 3 Image model. That model rewrites the prompt before generating (prompt expansion) and uses a different text encoder. FLUX.1 Dev does neither, so the BFL advice is used selectively:

- **Included:** general writing advice (prose structure, describing light, replacing negative prompts, quoting text).
- **Left out:** JSON and layout (bounding box) prompts, `aspect_ratio`/`resolution` request fields, multi-reference editing and hex color codes.
- **Changed:** advice that depends on prompt expansion, such as starting from a very short prompt, and keyword-only camera and style terms.

Most of this guide has not been tested on images. Treat it as a strong starting point and compare results in your own workflow.

## How FLUX.1 Dev reads a prompt

Basis: verified in source for the encoder and token facts; community practice for the rest.

- **Two text encoders, one doing most of the work.** T5-XXL reads the whole prompt and supplies the content. CLIP-L supplies only a single summary vector (`comfy/text_encoders/flux.py`).
- **T5 is a language model.** It reads sentences and the relationships in them (who holds what, what is behind what). This is why prose works better than a keyword list.
- **The prompt is not rewritten.** The model receives your literal text. Whatever you leave unstated is filled with the model's defaults (see [FLUX.1 Dev defaults and how to counter them](#flux1-dev-defaults-and-how-to-counter-them)).
- **Write a caption, not an instruction.** Describe the image as if it exists: `A grey cat sleeps on a sunlit windowsill`. Leave out `create an image of`, `make sure`, `I want` and `should`.
- **Words are read literally.** A metaphor or a role word can be drawn as the thing it names (see the `guardians` example in [Subject and relationships](#subject-and-relationships)).

### Token limits

Basis: verified in source.

| Encoder | Limit | In words |
|---|---|---|
| T5-XXL | BFL's reference code encodes at most 512 tokens (`load_t5` in `src/flux/util.py`). ComfyUI does not cut the prompt off, but text beyond 512 tokens is more than the model was built for | Roughly 350 to 400 English words |
| CLIP-L | Only the first 77-token chunk contributes to the summary vector (`comfy/sd1_clip.py`) | Roughly the first 50 to 60 words |

A prompt of normal length (see [Length](#length)) stays far below the T5 limit.

### Prompt fields and guidance in ComfyUI

Basis: verified in source for what the nodes do; community practice for the recommended values.

- **`CLIPTextEncode`** sends the same text to both encoders. This is the simple default.
- **`CLIPTextEncodeFlux`** has a `t5xxl` field, a `clip_l` field and a `guidance` value. Put the full prompt in `t5xxl`. In `clip_l`, repeat the prompt or write a one-sentence summary of subject and style. The effect of the `clip_l` text is small.
- **Guidance is not CFG.** It is a value passed to the model (default 3.5, set in `CLIPTextEncodeFlux` or a `FluxGuidance` node). CFG stays at `1.0`.

Guidance changes how a prompt is rendered, so a suggested value can accompany a prompt:

| Guidance | Effect | Use for |
|---|---|---|
| 1.5 to 2.5 | More natural texture and light, looser prompt following | Candid photographs, painterly and hand-drawn styles |
| 3.0 to 4.0 | The balanced default | Most prompts |
| 4.0 to 5.0 | Stricter prompt following, more contrast and polish | Complex layouts, several subjects, text in the image |

## Quick procedure

A short order of work for writing or converting a prompt. The sections below explain each step.

1. Separate what the request fixes (subject, style, mood) from what it leaves open.
2. Open with the image type and medium.
3. Write the subject sentence. Give every must-have element its own sentence.
4. Add one concrete statement each for setting, light and framing.
5. Check the model's defaults and counter the ones this image must not have (see [FLUX.1 Dev defaults and how to counter them](#flux1-dev-defaults-and-how-to-counter-them)).
6. If the image shows people and variety is wanted, describe a specific person (see [Diversity in faces and people](#diversity-in-faces-and-people)).
7. Reread and remove every phrase that does not describe something visible: instructions, negations, quality tags and mood words without a visible form.
8. Check the length (see [Length](#length)).

## How FLUX prompts differ from SDXL prompts

| SDXL habit | FLUX approach |
|---|---|
| A pile of comma-separated keywords | A clear description with a subject, a setting and a camera and lighting relationship |
| A negative prompt | No negative prompt. Describe what you want to see instead (see [Working without negative prompts](#working-without-negative-prompts)) |
| Quality tags such as "masterpiece", "best quality" | Leave them out. Describe what a real camera would record |
| Weight syntax such as `(word:1.3)` | ComfyUI parses it for FLUX.1, but prefer emphasis through placement and detail (see [Weight syntax in ComfyUI](#weight-syntax-in-comfyui)) |
| Artist names and one-word style labels | Weak in FLUX.1 Dev. Name the medium and describe its visible traits (see [Style](#style)) |
| A new seed for a new face | A new seed gives nearly the same face. Change the description of the person instead (see [Diversity in faces and people](#diversity-in-faces-and-people)) |

In this project's FLUX workflows, negative prompts are not used and CFG is virtually always `1.0` (see [workflows/readme.md](workflows/readme.md)). At a CFG of exactly `1.0`, ComfyUI does not evaluate the negative prompt at all (verified in `comfy/samplers.py`), so text in it has no effect.

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

Basis: community practice.

FLUX.1 Dev does not expand a short prompt. A short prompt returns the model's defaults for everything it does not mention.

- **Default:** one paragraph of three to six sentences (roughly 40 to 120 words) that covers subject, setting, light and framing.
- **Shorter** (one or two sentences) when you want to see what the model does by itself, or as the first step of an iteration.
- **Longer** when there are several subjects, a specific layout or text in the image. Stay below roughly 300 words (see [Token limits](#token-limits)).

Add only details that describe something visible, and remove any sentence that does not. Strong prompts are specific, not necessarily long. The component list below is not a checklist to fill in every time.

## Prompt components

| Component | What it controls | Example phrases |
|---|---|---|
| Image type | Overall category and framing | `portrait`, `landscape`, `macro`, `bird's-eye view` |
| Subject | The main focus | `an elderly man with a long white beard`, `a single red rose` |
| Location | Setting and mood | `inside a dimly lit jazz club`, `in a dense forest after rain` |
| Style | Visual language | `fashion editorial photography`, `oil painting`, `anime illustration` |
| Camera | Framing, viewpoint, depth of field | `head and shoulders fill the frame`, `seen from ground level`, `the background is a soft blur` |
| Lighting | Contrast, mood, depth | `soft window light`, `golden hour sunlight`, `harsh direct flash` |
| Colors | Palette | `muted beige and forest green tones`, `monochrome black and white` |
| Effects | Visual treatment | `film grain`, `soft bloom`, `motion blur` |
| Additional elements | Supporting details | `wind-blown fabric`, `glowing reflections on wet pavement` |

Use one or two strong effects. Too many make the image unfocused.

### Subject and relationships

Basis: FLUX 3 guide for the first paragraph; observed here for the list.

Be specific when it matters: `a young woman with curly red hair` renders more consistently than `a woman`. For people, see also [Diversity in faces and people](#diversity-in-faces-and-people). When there are several subjects, describe how they relate in full sentences: who faces whom, who is in focus, who is asleep.

The points below come from iterating on a single scene (see [Worked example: iterating on a scene](#worked-example-iterating-on-a-scene)). They have not been tested beyond that scene.

- **Give every must-have element its own sentence.** An element mentioned only as a short clause in a list can be dropped entirely. In the cavern example, statues and crystals listed as clauses did not appear; a detailed sentence for each made them appear.
- **Say what the thing physically is, and state count and placement.** A role word such as `guardians` can be misread (in the example, as faces hanging from the ceiling). `One life-size carved stone statue of a human woman in each alcove, standing on a plinth, hands folded` is unambiguous.
- **State relative scale when it matters.** If size is not given, the model chooses it. Use a reference: `about the height of the statues`, `twice as tall as a person`.
- **Describe a new object part by part** (for a tree: trunk, branches, leaves, and what each is made of). This keeps it from blending into similar elements nearby, such as crystals on a wall.

### Style

Basis: community practice.

Name the medium in the first sentence, then describe what that medium looks like. A style label alone is a weak signal in FLUX.1 Dev, and artist names mostly have no effect.

| Category | Name the medium | Then describe what is visible |
|---|---|---|
| Photographic | `35mm film photograph`, `2000s digicam snapshot`, `polaroid` | `visible grain`, `a warm color cast`, `slightly blown highlights`, `hard shadows from an on-camera flash` |
| Cinematic | `film still`, `film noir` | `wide frame`, `teal shadows and orange skin tones`, `a horizontal lens flare`, `deep black shadows` |
| Painting | `oil painting`, `watercolor`, `gouache` | `thick visible brushstrokes`, `ridges of paint catching the light`, `pigment bleeding into wet paper`, `white paper showing through` |
| Drawing | `pencil sketch`, `ink drawing` | `cross-hatched shading`, `loose construction lines`, `an uneven ink line` |
| Illustration | `flat vector illustration`, `comic art` | `solid color shapes without gradients`, `thick black outlines`, `halftone dots` |
| Digital art | `concept art`, `matte painting`, `stylized 3D render` | `a painterly background behind a sharper subject`, `smooth simplified shapes`, `soft even light` |

Points to know:

- **FLUX.1 Dev leans toward a photograph or a polished render.** A non-photographic style needs more description than a photograph does. Tie the description to surfaces in the scene: `her coat is painted in broad flat strokes of blue`.
- **Keep photo terms out of non-photographic prompts.** Lens names, f-numbers, `bokeh` and `film grain` pull the image back toward a photograph.
- **Film stock and camera names are weak signals.** `Kodak Portra 400` or `Hasselblad` can be added to a photographic prompt, but they do not replace a description of the look.
- **Be concrete about era and look.** `The grainy texture of mid-20th-century color film` says more than `vintage`.
- **Some styles are out of reach by prompt alone.** A specific artist's style or a strongly stylized look may need a LoRA.

### Natural versus built settings

Basis: observed here.

Material and origin words steer a setting toward looking grown or constructed. In the cavern example, `circular`, `symmetrical` and `arched` produced a built hall with smooth walls and a skylight. `Vast natural cavern`, `irregular`, `raw rock`, `craggy`, `hewn into the rock` and `chisel marks` produced a natural cave with carved alcoves.

| For a natural look | For a built look |
|---|---|
| `natural cavern`, `irregular rock walls`, `uneven vaulted ceiling` | `hall`, `chamber with smooth plastered walls` |
| `raw rock`, `craggy`, `weathered` | `masonry`, `polished`, `cut stone blocks` |
| `hewn into the rock`, `chisel marks` | `built`, `architectural`, `symmetrical`, `ordered rows of arches` |

### Camera and lens

Basis: community practice for the table; observed here for the viewpoint note.

Describe what the picture looks like first. A lens term can be added, but focal lengths and f-numbers alone are weak signals in FLUX.1 Dev.

| Goal | Describe the picture | Optional lens term |
|---|---|---|
| Blurred background | `only her face is in focus, the street behind her is a soft blur` | `shallow depth of field`, `f/1.8` |
| Everything sharp | `sharp from the rocks in the foreground to the distant mountains` | `deep depth of field`, `f/11` |
| Wide view | `wide shot showing the whole room`, `the figure is small in the frame` | `24mm wide-angle lens` |
| Natural perspective | `seen from eye level, a few steps away` | `35mm lens`, `50mm lens` |
| Tight portrait | `head and shoulders fill the frame` | `85mm portrait lens` |
| Compressed distance | `the distant mountains loom large directly behind the house` | `telephoto lens` |
| Extreme close-up | `extreme close-up of a bee's eye filling the frame` | `macro lens` |
| Widescreen cinema look | `wide frame, oval out-of-focus highlights` | `anamorphic lens` |

FLUX.1 Dev blurs backgrounds by default. A sharp background has to be asked for (see [FLUX.1 Dev defaults and how to counter them](#flux1-dev-defaults-and-how-to-counter-them)).

If a result keeps pulling back further than you want, state the subject and its framing first and move environmental details later.

The viewpoint also decides what is visible. In the cavern example, a view from the center of the room hid the alcoves around the edge; `wide shot from a low angle near the entrance, looking across the chamber` showed them.

### Lighting

Basis: FLUX 3 guide.

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

Basis: community practice.

Say where things are in the frame. The name of a technique can be added, but the name alone is a weak signal.

| Technique | Example phrase |
|---|---|
| Rule of thirds | `the lighthouse stands in the right third of the frame, the horizon in the lower third` |
| Leading lines | `a wet road runs from the bottom left corner to the house in the distance` |
| Foreground and background layers | `a large boulder in the foreground, mountains far behind it` |
| Low angle | `seen from ground level, looking up at the tower` |
| High angle | `seen from directly above, the city blocks forming a grid` |
| Dutch angle | `the camera is tilted, so the horizon runs diagonally across the frame` |
| Symmetry | `the corridor is centered and both walls mirror each other` |
| Negative space | `a small figure in the lower left corner, the rest of the frame empty sky` |

### Colors

Basis: community practice.

Lighting already influences color, but explicit palette direction keeps the image coherent: `warm orange and pink sunset colors`, `desaturated pastel palette`, `a futuristic busy city, purple and green color scheme`.

Name each color in plain words and attach it to its object in the same phrase: `a mustard-yellow raincoat`, `a door painted deep teal`. A color named far from its object can spread to other things in the scene.

Do not use hex codes. They are a feature of BFL's newer hosted models and are not reliable in FLUX.1 Dev. Write `deep orange-red` instead of `#FF5733`.

## FLUX.1 Dev defaults and how to counter them

Basis: community practice.

FLUX.1 Dev fills everything a prompt leaves open with the same few choices. Counter a default only when the image must not have it.

| Default | To counter it |
|---|---|
| A blurred background, even in wide scenes | Describe the background in detail and state that it is in focus. Leave out `portrait`, `bokeh` and `cinematic` |
| Smooth, flawless skin | `visible pores`, `fine lines`, `uneven skin tone`, `a few blemishes`. A lower guidance value also helps |
| A polished commercial look | Name an ordinary way of taking the picture: `casual phone snapshot`, `direct on-camera flash`, `mixed indoor lighting`, `slightly off-center framing`. Leave out `professional`, `cinematic` and `stunning` |
| A centered subject, posed and facing the camera | State where the subject is in the frame, where they look and what they are doing at this moment |
| Young, attractive people with the same face | State age and build, and see [Diversity in faces and people](#diversity-in-faces-and-people) |
| A photograph or glossy render when the style is weakly stated | Name the medium first and describe its surface (see [Style](#style)) |

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

## Diversity in faces and people

FLUX.1 Dev produces far less face variety per seed than SDXL. With one prompt and many seeds, the people look like close relatives: similar bone structure, smooth skin and often a cleft chin (the "FLUX face").

Two properties of the model are the usual explanation:

- **It is guidance-distilled.** The guidance value is an input to the model, not real classifier-free guidance. Distillation narrows the range of outputs toward the most typical result.
- **The prompt dominates the seed.** The text conditioning decides most of the image, and the seed changes details more than identity.

The practical rule: **in SDXL, variety comes from the seed; in FLUX, variety comes from the prompt.** To get a different person, describe a different person.

Use this section when the goal is exploration: many images to pick from. When the goal is the same character across images, do the opposite and keep the person's description identical in every prompt.

The prompt techniques below are community practice and have not been tested in this project. The `{a|b|c}` syntax was verified in the ComfyUI source, not by testing images.

### Describe a specific person

A generic subject (`a woman`, `a young man`) returns the default face. Each concrete attribute moves the result away from it.

| Lever | Example phrases |
|---|---|
| Full name | `Margit Kowalczyk`, `Tevita Fonua` |
| Exact age | `52-year-old` instead of `middle-aged` |
| Origin | `Moroccan`, `from northern Finland` |
| Occupation and life | `ferry mechanic`, `retired schoolteacher`, `night-shift nurse` |
| Face structure | `broad nose`, `deep-set eyes`, `heavy eyebrows`, `narrow chin`, `round face`, `wide-set eyes` |
| Skin and age detail | `freckles across the nose`, `crow's feet`, `sun-weathered skin`, `faint acne scars` |
| Hair | `cropped grey hair`, `thick black braid`, `receding hairline` |
| Build and expression | `stocky`, `wiry`, `lopsided smile`, `tired eyes` |

Points to know:

- **Use an invented full name.** A first and last name shifts the face, although the model knows nobody by that name. Put it in the subject sentence: `Portrait photograph of Margit Kowalczyk, a 52-year-old ferry mechanic with a broad nose and cropped grey hair`. Do not use the name of a real, known person.
- **Abstract diversity words do little.** `unique facial features`, `distinctive appearance` and `diverse` describe nothing visible. Name the feature instead.
- **Beauty words pull toward the default face.** Leave out `beautiful`, `attractive`, `stunning` and `model` unless the request asks for that look.
- **Start with two or three facial features.** Add more only if the face still looks generic.
- **Context changes the person too.** An occupation, a place and a moment (`photographed during a field expedition`) give the model more to build a person from. The same context returns the same person, so vary the context along with the face.

### Vary across a set of prompts

When one request produces several prompts, vary the people deliberately:

- Spread age, origin, face shape, build and hair across the set. Changing only the name is not enough.
- Check the set for your own repeated defaults (the same age range, the same skin detail, the same expression in every prompt).
- Vary setting, light and framing as well when the request allows it.

### Randomizing inside one prompt

ComfyUI can pick a random option each time a prompt is queued. Write the options as `{a|b|c}`:

```text
Portrait photograph of {Aiko Tanaka, a Japanese|Leila Benali, a Moroccan|Ingrid Solheim, a Norwegian|Camila Duarte, a Brazilian} {botanist|welder|bus driver|cellist} aged {24|37|51|68}, with a {round|long|square|heart-shaped} face, {deep-set|wide-set|hooded} eyes and a {broad|narrow|aquiline} nose.
```

| Syntax | Effect |
|---|---|
| `{a\|b\|c}` | One option, chosen at random each time the prompt is queued |
| `{a\|b\|}` | An empty option is allowed: sometimes nothing is inserted |
| `{a\|{b\|c}}` | Groups can be nested |
| `\{`, `\}` and `\\|` | Literal `{`, `}` and `\|` |
| `//` to end of line, `/* ... */` | Removed as a comment |

Points to know:

- **It runs in the ComfyUI browser frontend.** A prompt sent directly to the API is not processed and the braces reach the model as text.
- **One choice per queued prompt.** A batch size above 1 uses the same text for the whole batch. For different people, queue the prompt many times with batch size 1.
- **Literal braces are consumed**, as parentheses are (see [Weight syntax in ComfyUI](#weight-syntax-in-comfyui)). Escape them with a backslash.
- **`//` starts a comment.** Text after `//` on a line is removed, which also cuts a URL written in a prompt.
- **Options are chosen independently.** Put attributes that belong together in one option, as the name and origin are in the example above.
- **It applies to the vanilla `CLIPTextEncode` and `CLIPTextEncodeFlux` nodes.** A custom prompt node may behave differently or offer its own wildcard files.

### Levers outside the prompt

These are workflow settings, not prompt wording, and none has been tested in this project. They are listed so the prompt is not expected to do everything.

- **Lower guidance.** Values of 2.0 to 3.0 instead of 3.5 give more natural skin and somewhat more variety (see [Prompt fields and guidance in ComfyUI](#prompt-fields-and-guidance-in-comfyui)).
- **An SDXL first pass.** Generate a small image in SDXL for its variety, then refine it with FLUX image-to-image at a denoise of roughly 0.5 to 0.7.
- **Noise on the conditioning, or a stochastic sampler.** Both restore some variety from the seed.
- **A different model.** De-distilled FLUX.1 Dev variants, Chroma and FLUX.1 Krea Dev are reported to be less locked to one face.
- **Face LoRAs.** They can help, but many replace one default face with another.

Two things that do not add face variety: a wider shot (it makes the face smaller, so the sameness is harder to see) and Redux (it produces variations of one image).

### Example

**Before**

```text
Portrait photograph of a beautiful woman in a café, soft window light from the left, 85mm lens at f/2.
```

**After**

```text
Portrait photograph of Leila Benali, a 47-year-old Moroccan bookbinder with a long face, heavy eyebrows, an aquiline nose and grey-streaked black hair tied back. She sits at a café table with ink-stained fingers around a glass of tea. Soft window light from the left, 85mm lens at f/2.
```

What changed:

- `beautiful` was dropped.
- The person got a name, an exact age, an origin, an occupation and three facial features.
- One small detail from her life (`ink-stained fingers`) ties the person to the scene.

## Text in images

Basis: FLUX 3 guide for the method; community practice for the limits.

FLUX.1 Dev renders a few words of large text fairly reliably. Long sentences, small print and several separate text blocks often come out garbled.

For each block of text, state three things:

1. **The exact words**, in quotation marks, with the capitalization and punctuation you want.
2. **Where it goes** in the frame.
3. **The type:** weight, case, color and character.

Example: `a paper poster on the wall that reads "OPEN LATE", in bold white sans-serif capitals`.

Tips:

- Front-load the text description and keep strings short. Long strings are harder to render accurately.
- Mark line breaks and direction in the description (`arranged in two centered lines`).
- Proofread every quoted string in the output.
- A guidance value of 4.0 or higher can help text come out correctly.

## Common mistakes

- A long keyword pile with no clear subject, scene hierarchy, camera and lighting relationship.
- Instructions (`create an image of`, `make sure that`) instead of a description of the image.
- A very short prompt, with the expectation that the model will fill in a good image. It fills in its defaults (see [FLUX.1 Dev defaults and how to counter them](#flux1-dev-defaults-and-how-to-counter-them)).
- Quality tags ("masterpiece", "highly detailed", "ultrarealistic") used in place of concrete description.
- Negative phrasing ("no hat", "not blurry") instead of a positive description.
- A style label, artist name, camera model or f-number with no description of the look it stands for.
- Photo terms (`bokeh`, `85mm`, `film grain`) in a prompt for a painting or illustration.
- Hex color codes, or parameter syntax copied from another image platform.
- Stacking many effects, or mixing styles that do not belong together.
- Expecting a new seed to give a new face, or describing a person only as `a woman` or `a man` when variety is wanted (see [Diversity in faces and people](#diversity-in-faces-and-people)).
- Asking for variety with abstract words (`unique`, `distinctive`, `diverse`) instead of concrete features.

Observed in one scene in this project:

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
Portrait photograph of a woman with long red hair standing among tall pine trees at sunset. Warm, low sunlight falls on her face from the left and catches loose strands of her hair. Her head and shoulders fill the frame, and the forest behind her is a soft blur of amber light and deep green. Natural skin with visible pores and a few freckles, fine film grain.
```

What changed:

- Quality tags and `8k` were dropped; skin and film detail describe the look instead.
- `bokeh` became a description of the framing and of the blurred forest. No lens or f-number is needed.
- The weight syntax and `beautiful` were removed; the subject is stated first in plain language.
- The woman stays as generic as in the original. For a less typical face, describe a specific person (see [Diversity in faces and people](#diversity-in-faces-and-people)).
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

Source code:

- [ComfyUI: `comfy/sd1_clip.py`](https://github.com/comfyanonymous/ComfyUI/blob/master/comfy/sd1_clip.py) (weight parsing, encoding, CLIP-L chunks)
- [ComfyUI: `comfy/text_encoders/flux.py`](https://github.com/comfyanonymous/ComfyUI/blob/master/comfy/text_encoders/flux.py) (FLUX tokenizers, which encoder outputs are used)
- [ComfyUI: `comfy_extras/nodes_flux.py`](https://github.com/comfyanonymous/ComfyUI/blob/master/comfy_extras/nodes_flux.py) (`CLIPTextEncodeFlux` and `FluxGuidance`)
- [ComfyUI: `comfy/samplers.py`](https://github.com/comfyanonymous/ComfyUI/blob/master/comfy/samplers.py) (the negative prompt is skipped at CFG 1.0)
- [ComfyUI frontend: `dynamicPrompts.ts`](https://github.com/Comfy-Org/ComfyUI_frontend/blob/main/src/extensions/core/dynamicPrompts.ts) (the `{a|b|c}` extension; the parser is `processDynamicPrompt` in `formatUtil.ts`)
- [BFL: `src/flux/util.py`](https://github.com/black-forest-labs/flux/blob/main/src/flux/util.py) (reference text encoder setup and token limits)

Prompting guides (the BFL guides are written for FLUX 3 Image):

- [BFL: FLUX Prompting Guide](https://docs.bfl.ai/guides/prompting_summary)
- [BFL: Building a Good Prompt](https://docs.bfl.ai/guides/prompting_unified_building)
- [BFL: Style, Aesthetics & Text](https://docs.bfl.ai/guides/prompting_unified_style)
- [BFL: Technical Parameters](https://docs.bfl.ai/guides/prompting_unified_technical)
- [BFL: Prompt Reference](https://docs.bfl.ai/guides/prompting_unified_reference)
- [PrompTessor: FLUX Prompt Generator](https://promptessor.com/flux-prompt-generator)