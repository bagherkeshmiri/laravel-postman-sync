<?php

namespace BagherKeshmiri\PostmanSync;

class BuildReport
{
    /** @var array<string, string> shape key => folder it lives in */
    public array $kept = [];

    /** @var list<string> */
    public array $removed = [];

    /** @var list<string> */
    public array $added = [];

    /** @var list<string> */
    public array $variables = [];

    public int $queries = 0;

    public int $bodies = 0;

    public function endpoints(): int
    {
        return count($this->kept) + count($this->added);
    }

    public function changed(): bool
    {
        return $this->removed !== [] || $this->added !== [] || $this->variables !== [] || $this->queries > 0 || $this->bodies > 0;
    }
}
