# Blade/chart

SVG chart components for data visualisation.

## Files

- **area.blade.php** -- Area chart (filled line chart)
- **axis.blade.php** -- Chart axis container
- **cursor.blade.php** -- Interactive cursor/crosshair overlay
- **legend.blade.php** -- Chart legend
- **line.blade.php** -- Line chart series
- **point.blade.php** -- Data point marker
- **summary.blade.php** -- Chart summary/stats display
- **svg.blade.php** -- SVG container wrapper
- **tooltip.blade.php** -- Hover tooltip container
- **viewport.blade.php** -- Viewable chart area with coordinate system

## Subdirectories

- **tooltip/** -- `heading.blade.php` (tooltip title), `value.blade.php` (tooltip data value)
- **axis/** -- `grid.blade.php` (grid lines), `line.blade.php` (axis line), `mark.blade.php` (axis label), `tick.blade.php` (tick mark)

## Usage

```blade
<core:chart.svg>
    <core:chart.viewport>
        <core:chart.line :data="$series" />
        <core:chart.axis position="bottom" />
    </core:chart.viewport>
    <core:chart.legend :items="$items" />
</core:chart.svg>
```
