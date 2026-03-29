# Media/Abstracts/ — Media Base Classes

## Abstract Classes

| Class | Purpose |
|-------|---------|
| `Image` | Base class for image handling operations. |
| `MediaConversion` | Abstract base for all media conversions (image resizing, thumbnail generation, video processing). Provides common functionality: queueing for large files (threshold configurable via `media.queue_threshold_mb`), progress reporting via `ConversionProgress` event, and storage abstraction. |

Concrete implementations in `Core\Media\Conversions\`.
