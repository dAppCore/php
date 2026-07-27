# Media/Image/ — Image Processing

Image optimisation and processing utilities.

## Files

| File | Purpose |
|------|---------|
| `ImageOptimizer` | Main optimiser — resizes, compresses, and converts images. Includes memory safety checks (GD needs 5-6x image size). |
| `ImageOptimization` | Optimisation configuration and pipeline orchestration. |
| `ExifStripper` | Removes EXIF metadata from images for privacy (GPS coordinates, camera info). |
| `ModernFormatSupport` | Detects and enables WebP/AVIF support based on server capabilities. |
| `OptimizationResult` | DTO containing optimisation results — original size, optimised size, savings percentage, format used. |
