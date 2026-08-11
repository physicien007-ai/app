<?php
declare(strict_types=1);

class ValidationException extends Exception {}

/**
 * Same rules as gridBuilder.ts / GridBuilder.kt: every word must resolve
 * to an in-bounds clue cell + letter run, every shared cell must agree on
 * its letter, and — critically — every cell in the grid must end up
 * filled. That last rule is what guarantees the "no black squares" look.
 *
 * buildGrid() is the single source of truth used by the CLI import script,
 * the admin editor's live preview, and the admin save step — so a puzzle
 * that previews cleanly is *guaranteed* to save cleanly and vice versa.
 */
class Validator
{
    private const STEP = [
        'RTL'  => [0, -1],
        'LTR'  => [0, 1],
        'DOWN' => [1, 0],
        'UP'   => [-1, 0],
    ];

    public static function stepFor(string $direction): array
    {
        if (!isset(self::STEP[$direction])) {
            throw new ValidationException("Unknown direction: $direction");
        }
        return self::STEP[$direction];
    }

    /** Multibyte-safe split of an Arabic string into individual letters. */
    private static function letters(string $answer): array
    {
        return preg_split('//u', $answer, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Returns the built grid: rows x cols array where each cell is either
     *   ['type' => 'clue', 'clues' => [['direction'=>.., 'text'=>.., 'wordId'=>..], ...]]
     * or
     *   ['type' => 'letter', 'letter' => .., 'wordIds' => [..]]
     * Throws ValidationException on any inconsistency.
     */
    public static function buildGrid(array $puzzle): array
    {
        $rows = (int)($puzzle['rows'] ?? 0);
        $cols = (int)($puzzle['cols'] ?? 0);
        $id   = $puzzle['id'] ?? '(unknown)';

        if ($rows <= 0 || $cols <= 0) {
            throw new ValidationException("Puzzle '$id': rows/cols must be positive integers");
        }
        if (empty($puzzle['words'])) {
            throw new ValidationException("Puzzle '$id': needs at least one word");
        }

        $grid = array_fill(0, $rows, array_fill(0, $cols, null));
        $inBounds = fn(int $r, int $c) => $r >= 0 && $r < $rows && $c >= 0 && $c < $cols;

        foreach ($puzzle['words'] as $w) {
            foreach (['id', 'direction', 'startRow', 'startCol', 'answer', 'clueText'] as $field) {
                if (!array_key_exists($field, $w) || $w[$field] === '') {
                    throw new ValidationException("Puzzle '$id': a word is missing '$field'");
                }
            }
            [$dr, $dc] = self::stepFor($w['direction']);
            $clueR = (int)$w['startRow'] - $dr;
            $clueC = (int)$w['startCol'] - $dc;

            if (!$inBounds($clueR, $clueC)) {
                throw new ValidationException("Puzzle '$id': clue cell for '{$w['id']}' is out of bounds");
            }
            $existingAtClue = $grid[$clueR][$clueC];
            if ($existingAtClue === null) {
                $grid[$clueR][$clueC] = [
                    'type'  => 'clue',
                    'clues' => [['direction' => $w['direction'], 'text' => $w['clueText'], 'wordId' => $w['id']]],
                ];
            } elseif ($existingAtClue['type'] === 'clue') {
                // A second word legitimately points from this same cell in a
                // different direction — renders client-side as two arrows.
                $grid[$clueR][$clueC]['clues'][] = ['direction' => $w['direction'], 'text' => $w['clueText'], 'wordId' => $w['id']];
            } else {
                throw new ValidationException("Puzzle '$id': clue cell for '{$w['id']}' collides with a letter cell at ($clueR,$clueC)");
            }

            $letters = self::letters($w['answer']);
            foreach ($letters as $i => $ch) {
                $r = (int)$w['startRow'] + $dr * $i;
                $c = (int)$w['startCol'] + $dc * $i;
                if (!$inBounds($r, $c)) {
                    throw new ValidationException("Puzzle '$id': word '{$w['id']}' out of bounds at letter $i");
                }
                if ($grid[$r][$c] === null) {
                    $grid[$r][$c] = ['type' => 'letter', 'letter' => $ch, 'wordIds' => [$w['id']]];
                } elseif ($grid[$r][$c]['type'] === 'clue') {
                    throw new ValidationException("Puzzle '$id': word '{$w['id']}' overlaps a clue cell at ($r,$c)");
                } elseif ($grid[$r][$c]['letter'] !== $ch) {
                    throw new ValidationException(
                        "Puzzle '$id': letter mismatch at ($r,$c) — '{$grid[$r][$c]['letter']}' vs '$ch' (word '{$w['id']}')"
                    );
                } else {
                    $grid[$r][$c]['wordIds'][] = $w['id'];
                }
            }
        }

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                if ($grid[$r][$c] === null) {
                    throw new ValidationException("Puzzle '$id': cell ($r,$c) is empty — every cell must be a clue or a letter");
                }
            }
        }

        return $grid;
    }

    public static function validatePuzzle(array $puzzle): void
    {
        self::buildGrid($puzzle);
    }
}
