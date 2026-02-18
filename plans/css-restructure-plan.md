# CSS Restructure Plan for Rosalyn's Hotel 2026

## Current State Analysis
- Single consolidated `main.css` file (8,748 lines)
- Well-organized with clear sections using comment headers
- Issues identified:
  - Button styles scattered in multiple locations (`.btn` conflicts)
  - Some redundant styles across different sections
  - Large file size makes maintenance difficult

## Proposed New Structure

### Directory Structure
```
css/
├── main.css (entry point with imports)
├── base/
│   ├── variables.css
│   ├── reset.css
│   ├── typography.css
│   └── layout.css
├── components/
│   ├── buttons.css
│   ├── forms.css
│   ├── header.css
│   ├── footer.css
│   ├── cards.css
│   ├── modal.css
│   └── loader.css
├── sections/
│   ├── hero.css
│   ├── booking.css
│   ├── rooms.css
│   ├── restaurant.css
│   ├── events.css
│   ├── wellness.css
│   └── home.css
└── utilities/
    ├── animations.css
    ├── spacing.css
    └── responsive.css
```

## Key Improvements

### 1. Conflict Resolution
- **Button Conflicts**: Consolidate all `.btn` styles into `components/buttons.css`
- Remove duplicate `.btn-submit` styles and create consistent button variants
- Standardize button naming: `.btn`, `.btn--primary`, `.btn--secondary`, etc.

### 2. Naming Conventions
- **BEM Methodology**: Use Block-Element-Modifier pattern
  - `.btn` (block)
  - `.btn__icon` (element)
  - `.btn--large` (modifier)
- **Consistent Prefixes**:
  - Section prefixes: `.booking-`, `.room-`, `.restaurant-`
  - Component prefixes: `.card-`, `.form-`, `.modal-`

### 3. Variable Optimization
- Keep essential design tokens only
- Remove unused variables
- Group variables logically:
  ```css
  :root {
    /* Colors */
    --color-primary: #8B7355;
    --color-secondary: #1A1A1A;
    
    /* Typography */
    --font-primary: 'Cormorant Garamond', serif;
    --font-secondary: 'Jost', sans-serif;
    
    /* Spacing */
    --space-sm: 0.5rem;
    --space-md: 1rem;
    --space-lg: 2rem;
  }
  ```

### 4. Component-Based Architecture
- **Base Layer**: Variables, reset, typography, layout
- **Components**: Reusable UI elements
- **Sections**: Page-specific layouts
- **Utilities**: Helper classes and animations

### 5. Performance Optimizations
- Critical CSS inline for above-the-fold content
- Non-critical CSS loaded asynchronously
- Minified production builds
- Remove unused CSS through analysis

## Implementation Strategy

### Phase 1: Extract Base Layer
1. Extract variables, reset, typography, layout to separate files
2. Optimize and clean up each file
3. Ensure no dependencies between base files

### Phase 2: Component Extraction
1. Extract each component to its own file
2. Resolve conflicts (especially buttons)
3. Apply BEM naming conventions
4. Remove component-specific styles from sections

### Phase 3: Section Organization
1. Group page-specific styles
2. Remove component overlaps
3. Optimize for maintainability

### Phase 4: Utilities
1. Extract animations, spacing, responsive utilities
2. Create consistent utility classes
3. Remove redundant utilities

### Phase 5: Integration & Testing
1. Update main.css with new import structure
2. Test all pages for visual consistency
3. Performance testing and optimization
4. Documentation creation

## Benefits
- **Maintainability**: Easier to find and modify styles
- **Scalability**: Simple to add new components/sections
- **Performance**: Smaller, more focused CSS files
- **Collaboration**: Clear structure for team development
- **Debugging**: Isolated components make issues easier to trace

## File Size Targets
- Current: 8,748 lines in single file
- Target: ~2,000 lines total across modular files
- Critical CSS: ~500 lines inline
- Non-critical CSS: Loaded asynchronously