import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "tools" / "validate_flux_prompt.py"


class FluxPromptValidatorTest(unittest.TestCase):
    def run_validator(self, *args, input_text=None):
        return subprocess.run(
            [sys.executable, str(SCRIPT), *args],
            input=input_text,
            text=True,
            encoding="utf-8",
            capture_output=True,
            check=False,
        )

    def test_short_prompt_from_stdin(self):
        result = self.run_validator(input_text='A café at dawn. The sign reads "OPEN!"')
        self.assertEqual(result.returncode, 0)
        self.assertIn("Words: 8", result.stdout)
        self.assertIn("Sentences: approximately 2", result.stdout)
        self.assertIn("shorter prompts", result.stdout)

    def test_word_limit_boundaries(self):
        for count, expected in ((299, 0), (300, 1), (349, 1), (350, 1)):
            with self.subTest(count=count):
                result = self.run_validator("--text", " ".join(["word"] * count))
                self.assertEqual(result.returncode, expected)
                self.assertEqual("below roughly 300 words" in result.stdout, count >= 300)
                self.assertEqual("350-400 English words" in result.stdout, count >= 350)

    def test_options_use_the_longest_expanded_word_count(self):
        prompt = " ".join(["word"] * 298) + " {single|two words|{one|three word choice}}"
        result = self.run_validator("--text", prompt)
        self.assertEqual(result.returncode, 1)
        self.assertIn("Words: 301", result.stdout)
        self.assertIn("maximum", result.stdout)

        shorter = self.run_validator("--text", " ".join(["word"] * 298) + " {one|two}")
        self.assertEqual(shorter.returncode, 0)
        self.assertIn("Words: 299", shorter.stdout)

    def test_options_account_for_word_boundaries_and_empty_choices(self):
        for prompt, count in (("{one two|one two }three", 3), ("a{ |}b", 2)):
            with self.subTest(prompt=prompt):
                result = self.run_validator("--text", prompt)
                self.assertEqual(result.returncode, 0)
                self.assertIn(f"Words: {count} ", result.stdout)

    def test_escaped_syntax_and_comments(self):
        prompt = r"literal \{one|two\} {a\|b|three words} // ignored words" + "\nend /* not counted */"
        result = self.run_validator("--text", prompt)
        self.assertEqual(result.returncode, 0)
        self.assertIn("Words: 5 ", result.stdout)

    def test_unbalanced_dynamic_syntax_is_an_error(self):
        for prompt in ("before {one|two", "before one}", "before /* comment"):
            with self.subTest(prompt=prompt):
                result = self.run_validator("--text", prompt)
                self.assertEqual(result.returncode, 2)
                self.assertIn("Error", result.stderr)

    def test_file_and_paragraphs(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "prompt.txt"
            path.write_text("A café at dawn.\n\nSoft light falls on the snow.", encoding="utf-8-sig")
            result = self.run_validator("--file", str(path))
        self.assertEqual(result.returncode, 0)
        self.assertIn("Paragraphs: 2", result.stdout)
        self.assertIn("Sentences: approximately 2", result.stdout)

    def test_empty_and_unreadable_input_are_errors(self):
        empty = self.run_validator(input_text=" \n ")
        self.assertEqual(empty.returncode, 2)
        self.assertIn("prompt is empty", empty.stderr)
        with tempfile.TemporaryDirectory() as directory:
            missing = self.run_validator("--file", str(Path(directory) / "missing.txt"))
            invalid_path = Path(directory) / "invalid.txt"
            invalid_path.write_bytes(b"\xff")
            invalid = self.run_validator("--file", str(invalid_path))
        self.assertEqual(missing.returncode, 2)
        self.assertIn("Error reading prompt", missing.stderr)
        self.assertEqual(invalid.returncode, 2)
        self.assertIn("Error reading prompt", invalid.stderr)


if __name__ == "__main__":
    unittest.main()
