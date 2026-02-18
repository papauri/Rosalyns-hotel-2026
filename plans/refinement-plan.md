# Refinement and Final Polish Plan

## Objective
Tune the inertia scroll system to match the specific "Passalacqua" feel (slightly heavier, ultra-smooth) and clean up deprecated code from the enhancement suite.

## Proposed Changes

### 1. Tune Inertia Scroll (`js/inertia-scroll.js`)
- **Lerp Adjustment**: Decrease `this.config.lerp` from `0.08` to `0.07`. This increases the "weight" of the scroll, making it feel more luxurious and less twitchy.
- **Jitter Prevention**: In the `render()` loop, apply `Math.round()` to the scroll position before passing it to `window.scrollTo()`. This ensures that we land on exact pixel boundaries, preventing sub-pixel rendering artifacts (jitter) often seen on high-DPI displays with fixed elements.

### 2. Cleanup Legacy Code (`js/enhancements.js`)
- **Remove Deprecated Objects**: Delete the `MagicalScroll` and `ParallaxScroll` objects. These have been superseded by `InertiaScroll` and `ScrollPerformanceManager`.
- **Remove Initialization**: Remove calls to `.init()` for these deprecated objects.
- **Clean Exports**: Remove them from the `window.Enhancements` object and global window assignments.

### 3. verification
- Ensure `ScrollPerformanceManager` in `js/scroll-performance.js` continues to function correctly (it detects `window.inertiaScroll` presence).
- Ensure `AnimationCoordinator` in `js/animation-coordinator.js` continues to auto-register the inertia system.

## Execution Steps
1.  Edit `js/inertia-scroll.js` to apply tuning and rounding.
2.  Edit `js/enhancements.js` to remove legacy code.
3.  Notify user of completion.
