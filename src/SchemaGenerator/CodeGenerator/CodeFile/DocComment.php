<?php

namespace GraphQL\SchemaGenerator\CodeGenerator\CodeFile;

/**
 * Formats PHPDoc comments from schema descriptions and tags
 *
 * Class DocComment
 *
 * @package GraphQL\SchemaGenerator\CodeGenerator\CodeFile
 */
class DocComment
{
    /**
     * @param string|null $description Free text, may span multiple lines
     * @param string[]    $tags        Tag lines without the leading " * ", e.g. "@deprecated Use x instead"
     *
     * @return string The comment followed by a line break, or an empty string when there is nothing to document
     */
    public static function format(?string $description, array $tags = []): string
    {
        $lines = [];
        $description = trim((string) $description);
        if ($description !== '') {
            $lines = preg_split('/\R/', $description);
            if (!empty($tags)) {
                $lines[] = '';
            }
        }
        $lines = array_merge($lines, $tags);
        if (empty($lines)) {
            return '';
        }

        $comment = '/**' . PHP_EOL;
        foreach ($lines as $line) {
            // A literal "*/" would end the comment early
            $line = rtrim(str_replace('*/', '*\/', $line));
            $comment .= ($line === '' ? ' *' : " * $line") . PHP_EOL;
        }

        return $comment . ' */' . PHP_EOL;
    }
}
