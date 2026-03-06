<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generate a new Plug provider scaffold.
 *
 * Creates a provider in the Plug namespace with the operation-based
 * architecture (Auth, Post, Delete, etc.).
 *
 * Usage: php artisan make:plug Twitter --category=Social
 */
class MakePlugCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:plug
                            {name : The name of the provider (e.g., Twitter, Instagram)}
                            {--category=Social : Category (Social, Web3, Content, Chat, Business)}
                            {--auth : Include OAuth authentication operation}
                            {--post : Include posting operation}
                            {--delete : Include delete operation}
                            {--media : Include media upload operation}
                            {--all : Include all operations}
                            {--force : Overwrite existing provider}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Plug provider with operation-based architecture';

    /**
     * Valid categories for Plug providers.
     */
    protected const CATEGORIES = ['Social', 'Web3', 'Content', 'Chat', 'Business'];

    /**
     * Operations created during generation for summary table.
     *
     * @var array<array{operation: string, description: string}>
     */
    protected array $createdOperations = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $category = Str::studly($this->option('category'));

        if (! in_array($category, self::CATEGORIES)) {
            $this->newLine();
            $this->components->error("Invalid category [{$category}].");
            $this->newLine();
            $this->components->bulletList(self::CATEGORIES);
            $this->newLine();

            return self::FAILURE;
        }

        $providerPath = $this->getProviderPath($category, $name);

        if (File::isDirectory($providerPath) && ! $this->option('force')) {
            $this->newLine();
            $this->components->error("Provider [{$name}] already exists in [{$category}]!");
            $this->newLine();
            $this->components->warn('Use --force to overwrite the existing provider.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Creating Plug provider: <comment>{$category}/{$name}</comment>");
        $this->newLine();

        // Create directory structure
        File::ensureDirectoryExists($providerPath);
        $this->components->task('Creating provider directory', fn () => true);

        // Create operations based on flags
        $this->createOperations($providerPath, $category, $name);

        // Show summary table of created operations
        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Created Operations</>', '<fg=gray>Description</>');
        foreach ($this->createdOperations as $op) {
            $this->components->twoColumnDetail(
                "<fg=cyan>{$op['operation']}</>",
                "<fg=gray>{$op['description']}</>"
            );
        }

        $this->newLine();
        $this->components->info("Plug provider [{$category}/{$name}] created successfully!");
        $this->newLine();
        $this->components->twoColumnDetail('Location', "<fg=yellow>{$providerPath}</>");
        $this->newLine();

        $this->components->info('Usage example:');
        $this->line("  <fg=magenta>use</> Plug\\{$category}\\{$name}\\Auth;");
        $this->newLine();
        $this->line('  <fg=gray>$auth</> = <fg=cyan>new</> Auth(<fg=gray>\$clientId</>, <fg=gray>\$clientSecret</>, <fg=gray>\$redirectUrl</>);');
        $this->line('  <fg=gray>$authUrl</> = <fg=gray>\$auth</>-><fg=yellow>getAuthUrl</>();');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Get the path for the provider.
     */
    protected function getProviderPath(string $category, string $name): string
    {
        // Check for packages structure first (monorepo)
        $packagesPath = base_path("packages/core-php/src/Plug/{$category}/{$name}");
        if (File::isDirectory(dirname(dirname($packagesPath)))) {
            return $packagesPath;
        }

        // Fall back to app/Plug for consuming applications
        return base_path("app/Plug/{$category}/{$name}");
    }

    /**
     * Resolve the namespace for the provider.
     */
    protected function resolveNamespace(string $providerPath, string $category, string $name): string
    {
        if (str_contains($providerPath, 'packages/core-php/src/Plug')) {
            return "Core\\Plug\\{$category}\\{$name}";
        }

        return "Plug\\{$category}\\{$name}";
    }

    /**
     * Create operations based on flags.
     */
    protected function createOperations(string $providerPath, string $category, string $name): void
    {
        $namespace = $this->resolveNamespace($providerPath, $category, $name);

        // Always create Auth if --auth or --all or no specific options
        if ($this->option('auth') || $this->option('all') || ! $this->hasAnyOperation()) {
            $this->createAuthOperation($providerPath, $namespace, $name);
        }

        if ($this->option('post') || $this->option('all')) {
            $this->createPostOperation($providerPath, $namespace, $name);
        }

        if ($this->option('delete') || $this->option('all')) {
            $this->createDeleteOperation($providerPath, $namespace, $name);
        }

        if ($this->option('media') || $this->option('all')) {
            $this->createMediaOperation($providerPath, $namespace, $name);
        }
    }

    /**
     * Check if any operation option was provided.
     */
    protected function hasAnyOperation(): bool
    {
        return $this->option('auth')
            || $this->option('post')
            || $this->option('delete')
            || $this->option('media')
            || $this->option('all');
    }

    /**
     * Create the Auth operation.
     */
    protected function createAuthOperation(string $providerPath, string $namespace, string $name): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| {$name} OAuth Authentication
|--------------------------------------------------------------------------
|
| This is a generated template file. Replace the placeholder implementations
| below with your provider's actual API integration.
|
| See the Plug documentation for implementation guidance.
|
*/

/**
 * {$name} OAuth Authentication.
 *
 * Handles OAuth 2.0 authentication flow for {$name}.
 */
class Auth
{
    use BuildsResponse;
    use UsesHttp;

    protected string \$clientId;

    protected string \$clientSecret;

    protected string \$redirectUrl;

    protected array \$scopes = [];

    /**
     * Create a new Auth instance.
     */
    public function __construct(string \$clientId, string \$clientSecret, string \$redirectUrl)
    {
        \$this->clientId = \$clientId;
        \$this->clientSecret = \$clientSecret;
        \$this->redirectUrl = \$redirectUrl;
    }

    /**
     * Get the provider display name.
     */
    public static function name(): string
    {
        return '{$name}';
    }

    /**
     * Set OAuth scopes.
     *
     * @param  string[]  \$scopes
     */
    public function withScopes(array \$scopes): static
    {
        \$this->scopes = \$scopes;

        return \$this;
    }

    /**
     * Get the authorization URL for user redirect.
     */
    public function getAuthUrl(?string \$state = null): string
    {
        \$params = [
            'client_id' => \$this->clientId,
            'redirect_uri' => \$this->redirectUrl,
            'response_type' => 'code',
            'scope' => implode(' ', \$this->scopes),
        ];

        if (\$state) {
            \$params['state'] = \$state;
        }

        // TODO: [USER] Replace with your provider's OAuth authorization URL
        // Example: return 'https://api.twitter.com/oauth/authorize?' . http_build_query(\$params);
        return 'https://example.com/oauth/authorize?'.http_build_query(\$params);
    }

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCode(string \$code): Response
    {
        // TODO: [USER] Implement token exchange with your provider's API
        // Make a POST request to the provider's token endpoint with the authorization code
        return \$this->ok([
            'access_token' => '',
            'refresh_token' => '',
            'expires_in' => 0,
        ]);
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(string \$refreshToken): Response
    {
        // TODO: [USER] Implement token refresh with your provider's API
        // Use the refresh token to obtain a new access token
        return \$this->ok([
            'access_token' => '',
            'refresh_token' => '',
            'expires_in' => 0,
        ]);
    }

    /**
     * Revoke an access token.
     */
    public function revokeToken(string \$accessToken): Response
    {
        // TODO: [USER] Implement token revocation with your provider's API
        // Call the provider's revocation endpoint to invalidate the token
        return \$this->ok(['revoked' => true]);
    }

    /**
     * Get an HTTP client instance.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30);
    }
}

PHP;

        File::put("{$providerPath}/Auth.php", $content);
        $this->createdOperations[] = ['operation' => 'Auth.php', 'description' => 'OAuth 2.0 authentication'];
        $this->components->task('Creating Auth.php', fn () => true);
    }

    /**
     * Create the Post operation.
     */
    protected function createPostOperation(string $providerPath, string $namespace, string $name): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| {$name} Post Operation
|--------------------------------------------------------------------------
|
| This is a generated template file. Replace the placeholder implementations
| below with your provider's actual API integration.
|
| See the Plug documentation for implementation guidance.
|
*/

/**
 * {$name} Post Operation.
 *
 * Handles creating/publishing content to {$name}.
 */
class Post
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    /**
     * Create a new post/content.
     */
    public function create(string \$content, array \$options = []): Response
    {
        // TODO: [USER] Implement post creation with your provider's API
        // Example implementation:
        // \$response = \$this->http()
        //     ->withToken(\$this->accessToken())
        //     ->post('https://api.example.com/posts', [
        //         'text' => \$content,
        //         ...\$options,
        //     ]);
        //
        // return \$this->fromResponse(\$response);

        return \$this->ok([
            'id' => '',
            'url' => '',
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Schedule a post for later.
     */
    public function schedule(string \$content, \DateTimeInterface \$publishAt, array \$options = []): Response
    {
        // TODO: [USER] Implement scheduled posting with your provider's API
        return \$this->ok([
            'id' => '',
            'scheduled_at' => \$publishAt->format('c'),
        ]);
    }

    /**
     * Get an HTTP client instance.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30);
    }
}

PHP;

        File::put("{$providerPath}/Post.php", $content);
        $this->createdOperations[] = ['operation' => 'Post.php', 'description' => 'Content creation/publishing'];
        $this->components->task('Creating Post.php', fn () => true);
    }

    /**
     * Create the Delete operation.
     */
    protected function createDeleteOperation(string $providerPath, string $namespace, string $name): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| {$name} Delete Operation
|--------------------------------------------------------------------------
|
| This is a generated template file. Replace the placeholder implementations
| below with your provider's actual API integration.
|
| See the Plug documentation for implementation guidance.
|
*/

/**
 * {$name} Delete Operation.
 *
 * Handles deleting content from {$name}.
 */
class Delete
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    /**
     * Delete a post by ID.
     */
    public function post(string \$postId): Response
    {
        // TODO: [USER] Implement post deletion with your provider's API
        // Example implementation:
        // \$response = \$this->http()
        //     ->withToken(\$this->accessToken())
        //     ->delete("https://api.example.com/posts/{\$postId}");
        //
        // return \$this->fromResponse(\$response);

        return \$this->ok(['deleted' => true]);
    }

    /**
     * Get an HTTP client instance.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30);
    }
}

PHP;

        File::put("{$providerPath}/Delete.php", $content);
        $this->createdOperations[] = ['operation' => 'Delete.php', 'description' => 'Content deletion'];
        $this->components->task('Creating Delete.php', fn () => true);
    }

    /**
     * Create the Media operation.
     */
    protected function createMediaOperation(string $providerPath, string $namespace, string $name): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| {$name} Media Operation
|--------------------------------------------------------------------------
|
| This is a generated template file. Replace the placeholder implementations
| below with your provider's actual API integration.
|
| See the Plug documentation for implementation guidance.
|
*/

/**
 * {$name} Media Operation.
 *
 * Handles media uploads to {$name}.
 */
class Media
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    /**
     * Upload media from a file path.
     */
    public function upload(string \$filePath, array \$options = []): Response
    {
        // TODO: [USER] Implement media upload with your provider's API
        // Example implementation:
        // \$response = \$this->http()
        //     ->withToken(\$this->accessToken())
        //     ->attach('media', file_get_contents(\$filePath), basename(\$filePath))
        //     ->post('https://api.example.com/media/upload', \$options);
        //
        // return \$this->fromResponse(\$response);

        return \$this->ok([
            'media_id' => '',
            'url' => '',
        ]);
    }

    /**
     * Upload media from a URL.
     */
    public function uploadFromUrl(string \$url, array \$options = []): Response
    {
        // TODO: [USER] Implement URL-based media upload with your provider's API
        return \$this->ok([
            'media_id' => '',
            'url' => '',
        ]);
    }

    /**
     * Get an HTTP client instance.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60); // Longer timeout for uploads
    }
}

PHP;

        File::put("{$providerPath}/Media.php", $content);
        $this->createdOperations[] = ['operation' => 'Media.php', 'description' => 'Media file uploads'];
        $this->components->task('Creating Media.php', fn () => true);
    }

    /**
     * Get shell completion suggestions for arguments and options.
     */
    public function complete(
        \Symfony\Component\Console\Completion\CompletionInput $input,
        \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions
    ): void {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            // Suggest common social platform names
            $suggestions->suggestValues([
                'Twitter',
                'Instagram',
                'Facebook',
                'LinkedIn',
                'TikTok',
                'YouTube',
                'Mastodon',
                'Threads',
                'Bluesky',
            ]);
        }

        if ($input->mustSuggestOptionValuesFor('category')) {
            $suggestions->suggestValues(self::CATEGORIES);
        }
    }
}
