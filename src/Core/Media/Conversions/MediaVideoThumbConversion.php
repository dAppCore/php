<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Media\Conversions;

use Core\Media\Abstracts\Image;
use Core\Media\Abstracts\MediaConversion;
use Core\Media\Support\ImageResizer;
use Core\Media\Support\MediaConversionData;
use Core\Media\Support\TemporaryFile;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Support\Facades\File;

/**
 * Video thumbnail generation conversion.
 *
 * Extracts a frame from a video at a specified timestamp and generates
 * a resized thumbnail image. Requires FFmpeg to be installed.
 */
class MediaVideoThumbConversion extends MediaConversion
{
    protected float $atSecond = 0;

    /**
     * Get the engine name for this conversion.
     */
    public function getEngineName(): string
    {
        return 'VideoThumb';
    }

    /**
     * Check if this conversion can be performed.
     *
     * Only processes video files and requires FFmpeg installation.
     */
    public function canPerform(): bool
    {
        return $this->isVideo();
    }

    /**
     * Get the output file path.
     *
     * Video thumbnails are always saved as JPG.
     */
    public function getPath(): string
    {
        return $this->getFilePathWithSuffix('jpg');
    }

    /**
     * Set the timestamp (in seconds) to extract the frame from.
     */
    public function atSecond(float $value = 0): static
    {
        $this->atSecond = $value;

        return $this;
    }

    /**
     * Perform the video thumbnail generation.
     *
     * Returns null if FFmpeg is not installed.
     */
    public function handle(): ?MediaConversionData
    {
        if (! $this->isFFmpegInstalled()) {
            return null;
        }

        // Copy video to temporary location for processing
        $temporaryFile = TemporaryFile::make()->fromDisk(
            sourceDisk: $this->getFromDisk(),
            sourceFilepath: $this->getFilepath()
        );

        $thumbFilepath = $this->getFilePathWithSuffix('jpg', $temporaryFile->path());

        // Extract frame using FFmpeg
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => config('media.ffmpeg_path', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => config('media.ffprobe_path', '/usr/bin/ffprobe'),
        ]);

        $video = $ffmpeg->open($temporaryFile->path());
        $duration = $ffmpeg->getFFProbe()->format($temporaryFile->path())->get('duration');

        // Ensure seconds is within valid bounds
        $seconds = ($duration > 0 && $this->atSecond > 0)
            ? min($this->atSecond, floor($duration))
            : 0;

        $frame = $video->frame(TimeCode::fromSeconds($seconds));
        $frame->save($thumbFilepath);

        // Sometimes the frame is not saved, so we retry with the first frame
        // This is a workaround for edge cases in FFmpeg
        if ($this->atSecond !== 0.0 && ! File::exists($thumbFilepath)) {
            $frame = $video->frame(TimeCode::fromSeconds(0));
            $frame->save($thumbFilepath);
        }

        // Resize the thumbnail and save it to the destination disk
        ImageResizer::make($thumbFilepath)
            ->disk($this->getToDisk())
            ->path($this->getPath())
            ->resize(Image::MEDIUM_WIDTH, Image::MEDIUM_HEIGHT);

        // Clean up temporary files
        $temporaryFile->directory()->delete();

        return MediaConversionData::conversion($this);
    }

    /**
     * Check if FFmpeg is installed and accessible.
     */
    private function isFFmpegInstalled(): bool
    {
        $ffmpegPath = config('media.ffmpeg_path', '/usr/bin/ffmpeg');
        $ffprobePath = config('media.ffprobe_path', '/usr/bin/ffprobe');

        return file_exists($ffmpegPath) &&
            file_exists($ffprobePath) &&
            basename($ffmpegPath) === 'ffmpeg' &&
            basename($ffprobePath) === 'ffprobe';
    }
}
