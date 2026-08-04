<?php

namespace Core\Http;

/**
 * 表单请求验证 - 分离验证逻辑到独立类。
 *
 * 用法：
 *   class StorePostRequest extends FormRequest
 *   {
 *       public function rules(): array {
 *           return [
 *               'title'       => 'required|max:200',
 *               'content_md'  => 'required|min:10',
 *               'category_id' => 'required|integer',
 *               'status'      => 'required|in:draft,published,archived',
 *               'email'       => 'email',
 *               'tags'        => 'string',
 *           ];
 *       }
 *       public function messages(): array {
 *           return [
 *               'title.required' => '标题不能为空',
 *               'title.max'      => '标题不能超过 200 字',
 *               'content_md.min' => '内容至少 10 字',
 *           ];
 *       }
 *   }
 *
 *   // 控制器中
 *   public function store(StorePostRequest $request): Response {
 *       $data = $request->validated();  // 验证通过返回数据
 *       // 验证失败时自动重定向回表单页，错误信息闪存到 Session
 *   }
 */
abstract class FormRequest
{
    protected Request $request;
    protected Session $session;

    /** @var array<string, string> 验证错误 */
    protected array $errors = [];

    /** @var array<string, mixed> 验证后的数据 */
    protected array $validated = [];

    public function __construct()
    {
        $this->request = app(Request::class);
        $this->session = app(Session::class);
    }

    /**
     * 验证规则。
     *
     * @return array<string, string> [字段 => '规则1|规则2|...']
     */
    abstract public function rules(): array;

    /**
     * 自定义错误消息。
     *
     * @return array<string, string> [字段.规则 => 消息]
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * 验证是否通过。
     */
    public function passes(): bool
    {
        $rules = $this->rules();
        $messages = $this->messages();
        $all = $this->request->all();

        foreach ($rules as $field => $ruleStr) {
            $value = $all[$field] ?? null;
            $rulesArr = explode('|', $ruleStr);

            foreach ($rulesArr as $rule) {
                $error = $this->validateRule($field, $value, $rule, $all);
                if ($error !== null) {
                    $key = $field . '.' . $rule;
                    $this->errors[$field] = $messages[$key] ?? $error;
                    break;  // 该字段首个错误即停止
                }
            }

            // 验证通过的字段加入 validated
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return empty($this->errors);
    }

    /**
     * 执行验证，失败则重定向回表单页。
     */
    public function validate(): array
    {
        if (!$this->passes()) {
            $this->session->flashInput($this->request->all());
            foreach ($this->errors as $field => $msg) {
                $this->session->flash('error_' . $field, $msg);
            }
            $this->session->flash('error', '请检查输入：' . implode('；', $this->errors));

            $referer = $_SERVER['HTTP_REFERER'] ?? url('/');
            (new Response())->redirect($referer)->send();
            exit;
        }
        return $this->validated;
    }

    /**
     * 获取验证后的数据（需先调用 validate 或 passes）。
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * 获取验证错误。
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 是否未通过验证。
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * 单条规则验证。
     */
    private function validateRule(string $field, mixed $value, string $rule, array $all): ?string
    {
        // 解析规则名和参数：max:200 → ['max', ['200']]
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        return match ($name) {
            'required' => ($value === null || $value === '') ? "{$field} 不能为空" : null,
            'string'   => ($value !== null && !is_string($value)) ? "{$field} 必须是字符串" : null,
            'integer'  => ($value !== null && !preg_match('/^-?\d+$/', (string)$value)) ? "{$field} 必须是整数" : null,
            'numeric'  => ($value !== null && !is_numeric($value)) ? "{$field} 必须是数字" : null,
            'email'    => ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) ? "{$field} 邮箱格式不正确" : null,
            'url'      => ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) ? "{$field} URL 格式不正确" : null,
            'min'      => $this->validateMin($field, $value, $params),
            'max'      => $this->validateMax($field, $value, $params),
            'in'       => (!in_array($value, $params, true)) ? "{$field} 必须是 " . implode('/', $params) : null,
            'not_in'   => (in_array($value, $params, true)) ? "{$field} 不能是 " . implode('/', $params) : null,
            'confirmed'=> ($value !== ($all[$field . '_confirmation'] ?? null)) ? "{$field} 两次输入不一致" : null,
            'regex'    => ($value !== null && !preg_match('/' . ($params[0] ?? '') . '/', (string)$value)) ? "{$field} 格式不正确" : null,
            'date'     => ($value !== null && strtotime((string)$value) === false) ? "{$field} 日期格式不正确" : null,
            'alpha'    => ($value !== null && !preg_match('/^[a-zA-Z]+$/', (string)$value)) ? "{$field} 只能是字母" : null,
            'alpha_num'=> ($value !== null && !preg_match('/^[a-zA-Z0-9]+$/', (string)$value)) ? "{$field} 只能是字母和数字" : null,
            default    => null,
        };
    }

    private function validateMin(string $field, mixed $value, array $params): ?string
    {
        if ($value === null) return null;
        $min = (int)($params[0] ?? 0);
        $len = is_string($value) ? mb_strlen($value) : (is_array($value) ? count($value) : (int)$value);
        return $len < $min ? "{$field} 不能少于 {$min}" : null;
    }

    private function validateMax(string $field, mixed $value, array $params): ?string
    {
        if ($value === null) return null;
        $max = (int)($params[0] ?? PHP_INT_MAX);
        $len = is_string($value) ? mb_strlen($value) : (is_array($value) ? count($value) : (int)$value);
        return $len > $max ? "{$field} 不能超过 {$max}" : null;
    }
}
