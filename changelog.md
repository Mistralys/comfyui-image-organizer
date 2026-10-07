## 1.1.0 - Typed data and PHPStan level 9

**The LoRA overview and workflow path fixing can now read nested LoRA definition files.**
Image index and workflow files keep unknown keys when saved, and the codebase now has
its first automated tests.

- LoRAs: Overview and workflow path fixing now read the nested format as well as the flat one.
- Data: Image index and workflows preserve unknown keys when written back.
- Data: JSON files are read into typed data objects at the file boundaries.
- Tests: Added the first PHPUnit tests for data objects, session state, LoRAs and workflows.
- Analysis: PHPStan raised to level 9 with no findings and no baseline.

## 1.0.0 - Initial release
- First release with all initially planned features.
