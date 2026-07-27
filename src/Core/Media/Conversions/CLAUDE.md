# Media/Conversions/ — Media Conversion Implementations

Concrete implementations of `MediaConversion` for specific processing tasks.

## Conversions

| Class | Purpose |
|-------|---------|
| `MediaImageResizerConversion` | Resizes images to specified dimensions while maintaining aspect ratio. Prevents upscaling. Skips GIF files to preserve animation. Uses `ImageResizer`. |
| `MediaVideoThumbConversion` | Extracts thumbnail frames from video files for preview display. |

Both extend `Core\Media\Abstracts\MediaConversion` and can be queued via `ProcessMediaConversion` job.
