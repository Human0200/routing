<?php
namespace App;

class EmailParser
{
    private $imap;
    private $logger;

    public function __construct()
    {
        $this->logger = Logger::get();
        $this->connect();
    }

    private function connect(): void
    {
        $server   = Config::getImapServer();
        $login    = Config::getImapLogin();
        $password = Config::getImapPassword();

        $this->imap = @imap_open($server, $login, $password, OP_SILENT);

        if (! $this->imap) {
            $error = imap_last_error();
            $this->logger->error("IMAP connection failed", [
                'server' => $server,
                'login'  => $login,
                'error'  => $error,
            ]);
            throw new \RuntimeException("IMAP connection failed: $error");
        }

        $this->logger->info("Connected to IMAP server", ['mailbox' => $server]);
    }

    public function getMessages(): array
    {
        $total = imap_num_msg($this->imap);
        $this->logger->info("Found messages in mailbox", ['count' => $total]);

        $messages = [];
        for ($i = $total; $i >= 1; $i--) {
            try {
                $header    = imap_headerinfo($this->imap, $i);
                $structure = imap_fetchstructure($this->imap, $i);

                $fromAddress = isset($header->from[0])
                ? $this->parseAddress($header->from[0])
                : ['name' => '', 'email' => ''];

                $messages[] = [
                    'number'      => $i,
                    'subject'     => $this->decodeHeader($header->subject ?? 'No subject'),
                    'date'        => $header->date ?? null,
                    'message_id'  => $header->message_id ?? $this->generateMessageId($i),
                    'from'        => $fromAddress,
                    'attachments' => $this->getAttachments($i),
                    'body'        => $this->getMessageBody($i, $structure),
                ];

                $this->logger->debug("Processed message", ['number' => $i]);

            } catch (\Exception $e) {
                $this->logger->error("Error processing message", [
                    'number' => $i,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString(),
                ]);
            }
        }

        return $messages;
    }

    private function getMessageBody(int $msgNumber, $structure): string
    {
        if (! empty($structure->parts)) {
            return $this->getMultipartBody($msgNumber, $structure);
        }
        return $this->decodeContent(
            imap_fetchbody($this->imap, $msgNumber, 1),
            $structure->encoding ?? 0
        );
    }

    private function getMultipartBody(int $msgNumber, $structure): string
    {
        $body = '';
        foreach ($structure->parts as $partNum => $part) {
            $partId = $partNum + 1;
            if (strtolower($part->subtype ?? '') === 'plain') {
                $body .= $this->decodeContent(
                    imap_fetchbody($this->imap, $msgNumber, $partId),
                    $part->encoding ?? 0
                );
            }
        }
        return $body;
    }

    private function decodeContent(string $content, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($content),
            4 => quoted_printable_decode($content),
            default => $content
        };
    }

    private function decodeHeader(string $header): string
    {
        return iconv_mime_decode($header, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    }

    private function parseAddress($address): array
    {
        if (! $address) {
            return ['name' => '', 'email' => ''];
        }

        return [
            'name'  => $this->decodeHeader($address->personal ?? ''),
            'email' => $address->mailbox . '@' . $address->host,
        ];
    }

    private function getAttachments(int $msgNumber): array
    {
        $structure = imap_fetchstructure($this->imap, $msgNumber);
        return $this->findAttachments($structure);
    }

    private function findAttachments($structure, $prefix = ''): array
    {
        $attachments = [];

        if (! empty($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $partNum     = $prefix ? "$prefix." . ($index + 1) : (string) ($index + 1);
                $attachments = array_merge(
                    $attachments,
                    $this->findAttachments($part, $partNum)
                );
            }
            return $attachments;
        }

        if ($filename = $this->getFilename($structure)) {
            return [[
                'partNum'  => $prefix ?: '1',
                'filename' => $filename,
                'encoding' => $structure->encoding,
                'size'     => $structure->bytes ?? 0,
            ]];
        }

        return [];
    }

    private function getFilename($structure): ?string
    {
        $sources = [
            'dparameters' => $structure->dparameters ?? [],
            'parameters'  => $structure->parameters ?? [],
        ];

        foreach ($sources as $params) {
            foreach ($params as $param) {
                if (strtolower($param->attribute ?? '') === 'filename') {
                    return $this->decodeHeader($param->value);
                }
            }
        }

        if (strtolower($structure->disposition ?? '') === 'attachment') {
            foreach ($structure->dparameters ?? [] as $param) {
                if (strtolower($param->attribute ?? '') === 'filename') {
                    return $this->decodeHeader($param->value);
                }
            }
        }

        return null;
    }

    public function getAttachmentContent(int $msgNumber, string $partNum, int $encoding): string
    {
        $content = imap_fetchbody($this->imap, $msgNumber, $partNum);
        return $this->decodeContent($content, $encoding);
    }

    private function generateMessageId(int $msgNumber): string
    {
        return sprintf(
            "%d.%d.%d@%s",
            $msgNumber,
            time(),
            rand(1000, 9999),
            gethostname()
        );
    }

    public function __destruct()
    {
        if ($this->imap) {
            imap_close($this->imap);
            $this->logger->info("IMAP connection closed");
        }
    }
}
