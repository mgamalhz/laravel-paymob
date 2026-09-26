# Quality policy

This package runs Larastan at PHPStan level 5 with no baseline and enforces the Laravel Pint preset.

Level 5 is the strictest level the current codebase passes without suppressions. Level 6 currently exposes missing array value types across the public DTO transformer API and test webhook fixtures, missing Eloquent relation generics, and a few framework-facing methods without declared return types. Those are real typing improvements for a later focused change; they are not hidden behind a baseline or ignore rules here.
