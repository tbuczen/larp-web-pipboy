<?php

declare(strict_types=1);

$separator = '(?:/|%2f)';
$invitePath = '(?:i|%69)(?:n|%6e)(?:v|%76)(?:i|%69)(?:t|%74)(?:e|%65)';
$tokenCharacter = '(?:[a-f0-9]|%(?:3[0-9]|[46][1-6]))';
$pattern = '~'.$separator.$invitePath.$separator.$tokenCharacter.'{64}~i';

while (($line = fgets(STDIN)) !== false) {
    $redacted = preg_replace($pattern, '/invite/[redacted]', $line);
    if ($redacted === null || fwrite(STDOUT, $redacted) === false) {
        fwrite(STDERR, "Unable to write the redacted development server log.\n");
        exit(1);
    }
    fflush(STDOUT);
}

if (!feof(STDIN)) {
    fwrite(STDERR, "Unable to read the development server log.\n");
    exit(1);
}
