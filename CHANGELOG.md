# Changelog - Course Box Manager

## [1.9.36] - 2025-09-30

### Fixed
- **Mobile UX:** Responsive min-height for enroll box in popup
  - Desktop (>768px): Maintains min-height: 400px
  - Tablet (≤768px): Reduced to min-height: 300px
  - Mobile (≤480px): Adaptive min-height: auto
  - Fixes forced scroll on small devices (iPhone SE, 12 Mini)
  - Provides 30% more available screen space on mobile

### Improved
- **Code Quality:** Removed duplicate CSS rules
  - Eliminated conflicting `.cbm-popup-container .box` styles (53 lines)
  - Removed duplicate `.date-btn` definitions
  - Cleaner, more maintainable CSS structure

### Impact
- Better UX on devices ≤480px height
- No changes to desktop experience
- Consistent visual hierarchy across breakpoints

---

## [1.9.35] - 2025-09-16

### Changed
- Simplified close button - X without circle design

### Fixed
- Close button positioning in popup

---

## Previous Versions

See git history for versions 1.9.0 - 1.9.34

---

## Development Notes

### Version Numbering
- **Major.Minor.Patch** (Semantic Versioning)
- Patch: Bug fixes, CSS tweaks
- Minor: New features, significant changes
- Major: Breaking changes, major refactors
