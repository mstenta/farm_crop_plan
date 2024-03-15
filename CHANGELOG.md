# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.0-alpha1] 2024-03-15

This is the first official alpha release of the farmOS Crop Plan module.

It is considered "alpha" because it is still very much a proof-of-concept, and
is not going to be useful for most real-world crop planning. It can be used to
visualize plantings on a timeline, but using it for day-to-day management is
quite tedious. Moving forward, we hope to work as a community to identify and
prioritize next steps based on what would be most useful.

This project was originally started on farmOS v1 (see the `7.x-1.x` branch).
We started a `2.x` branch as a placeholder during farmOS v2 development, but
didn't have the resources to pick it back up again until farmOS v3. That is why
this release is `3.0.0-alpha1` and there are no other releases before it. The
full history is available in the Git commits if you are interested.

Here is a summary of the major features this release provides:

### Added

- "Crop" plan type, with a "Season" reference field
- "Crop planting" plan record type, with a "Plant" reference field, a "Seeding
  date" field, and fields for "Days to maturity", "Days to transplant", and
  "Days to harvest"
- Form for adding Plant assets to the plan, along with plan-specific metadata.
- Gantt chart visualization of Plant assets in the plan, using
  [svelte-gantt](https://github.com/ANovokmet/svelte-gantt), with the ability to
  view by plant type or by location.

[Unreleased]: https://github.com/mstenta/farmOS/compare/3.0.0-alpha1...HEAD
[3.0.0-alpha1]: https://github.com/mstenta/farmOS/releases/tag/3.0.0-alpha1
