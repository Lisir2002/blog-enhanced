<?php

namespace Core\Http;

/**
 * Response 抽象，链式调用。
 */
class Response
{
    private int $status = 200;
    /** @var array<string, string> */
    private array $headers = [];
    private string $body = '';

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function setContentType(string $type, string $charset = 'UTF-8'): static
    {
        $value = $charset ? "$type; charset=$charset" : $type;
        return $this->header('Content-Type', $value);
    }

    public function json(mixed $data, int $status = 200): static
    {
        $this->setContentType('application/json')
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
            ->setStatus($status);
        return $this;
    }

    public function redirect(string $url, int $status = 302): static
    {
        $this->setStatus($status)
            ->header('Location', $url)
            ->setBody('');
        return $this;
    }

    public function redirectRoute(string $name, array $params = []): static
    {
        $url = app(\Core\Router::class)->route($name, $params);
        return $this->redirect($url);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
        echo $this->body;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
