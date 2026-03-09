<?php

declare(strict_types=1);

namespace Core\Plug\Contract;

use Core\Plug\Response;

/**
 * Contract for comment/reply operations.
 */
interface Commentable
{
    /**
     * Post a comment or reply to existing content.
     *
     * @param  string  $text  Comment content
     * @param  string  $postId  ID of the post to comment on
     * @param  array  $params  Platform-specific options
     */
    public function comment(string $text, string $postId, array $params = []): Response;
}
