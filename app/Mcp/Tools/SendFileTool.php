<?php

namespace App\Mcp\Tools;

use App\Enums\FileType;
use App\Mcp\BaseMcpTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class SendFileTool extends BaseMcpTool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Send a file to the user. Max 50 MB.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        \Log::debug(sprintf('[TOOL CALL] %s tool called with params: ', get_class($this)), $request->all());

        $request->validate([
            'file_path' => 'required|string',
        ]);

        $file_path = $request->get('file_path');
        $is_url = (filter_var($file_path, FILTER_VALIDATE_URL) == $file_path);

        if ($is_url) {
            $file_type = $this->mimeToFileType($this->guessUrlMimeType($file_path));
        } else {
            if (!file_exists($file_path)) {
                return Response::error('The provided file path is not valid.');
            }
            $file_type = $this->mimeToFileType($this->guessFileMimeType($file_path));
        }

        if ($file_type === FileType::IMAGE) {
            // Change file type to OTHER if the image is larger than 10MB but less than or equal to 50MB
            if (filesize($file_path) > 10 * 1024 * 1024 && filesize($file_path) <= 50 * 1024 * 1024) {
                $file_type = FileType::OTHER;
            }
        }

        if (filesize($file_path) > 50 * 1024 * 1024) {
            return Response::error('The provided file is too large. Maximum allowed size is 50MB.');
        }

        switch ($file_type) {
            case FileType::IMAGE:
                $this->chat->sendChatAction('upload_photo');
                $this->chat->sendPhoto($file_path);
                break;
            case FileType::VIDEO:
                $this->chat->sendChatAction('upload_video');
                $this->chat->sendVideo($file_path);
                break;
            case FileType::AUDIO:
                $this->chat->sendChatAction('upload_voice');
                $this->chat->sendAudio($file_path);
                break;
            default:
                $this->chat->sendChatAction('upload_document');
                $this->chat->sendFile($file_path);
        }

        return Response::structured(pathinfo($file_path));
    }

    private function guessUrlMimeType(string $url): string
    {
        $headers = get_headers($url, 1);
        if ($headers && isset($headers['Content-Type'])) {
            return is_array($headers['Content-Type']) ? end($headers['Content-Type']) : $headers['Content-Type'];
        }
        return 'application/octet-stream'; // Default MIME type if not found
    }

    private function guessFileMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime_type ?: 'application/octet-stream';
    }

    private function mimeToFileType(string $mime_type): FileType
    {
        if (str_starts_with($mime_type, 'image/')) {
            return FileType::IMAGE;
        } elseif (str_starts_with($mime_type, 'video/')) {
            return FileType::VIDEO;
        } elseif (str_starts_with($mime_type, 'audio/')) {
            return FileType::AUDIO;
        }

        return FileType::OTHER;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()->description('REQUIRED. The full path or URL to the file to send. Max 50 MB'),
        ];
    }
}
