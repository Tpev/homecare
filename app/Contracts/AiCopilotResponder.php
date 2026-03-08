<?php

namespace App\Contracts;

interface AiCopilotResponder
{
    /**
     * @param  array<int,array{role:string,content:string}>  $conversation
     * @param  array<string,mixed>  $draft
     * @param  array<int,string>  $missingRequired
     * @return array{
     *   assistant_message:string,
     *   field_updates:array<string,mixed>,
     *   field_confidence:array<string,float>,
     *   needs_confirmation:array<int,string>,
     *   next_question:string,
     *   quick_replies:array<int,string>,
     *   safety_flags:array<int,string>,
     *   quality_hints:array<int,string>,
     *   model?:string,
     *   prompt_tokens?:int,
     *   completion_tokens?:int,
     *   latency_ms?:int
     * }
     */
    public function generate(array $conversation, array $draft, array $missingRequired): array;
}

