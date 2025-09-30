# Changelog - Course Box Manager

## [1.9.37] - 2025-09-30

### Changed
- **Price Priority:** WooCommerce is now the primary source for prices
  - Priority 1: WooCommerce product price (active, regular, sale)
  - Priority 2: ACF custom fields (`course_price`, `enroll_price`)
  - Priority 3: Default constants (749.99 / 1249.99)

- **Dynamic Currency:** Replaced hardcoded "USD" with WooCommerce currency
  - Uses `get_woocommerce_currency()` for dynamic currency display
  - Fallback to "USD" if WooCommerce not available

### Added
- **New helper methods in AbstractBox:**
  - `get_price_with_priority()`: Centralized price fetching logic
  - `get_currency()`: Get WooCommerce currency dynamically
  - `validate_price()`: Validate price values
  - `DEFAULT_BUY_PRICE` and `DEFAULT_ENROLL_PRICE` constants

### Improved
- **All Box types updated:**
  - BuyCourseBox: Uses new pricing system
  - EnrollCourseBox: Uses new pricing system
  - EnrollBuyBox: Handles both products with new system
- Better logging for price source debugging
- Consistent price handling across all box types

### Technical Details
- WooCommerce product prices take precedence over ACF fields
- Sale prices automatically detected and displayed
- Cleaner, more maintainable code structure
- Reduced code duplication

---

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
