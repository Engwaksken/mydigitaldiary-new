<?php

namespace App\Services\Ai;

class ScriptureReferenceService
{
    /**
     * Return 3-5 Bible references for a topic when the faith path is
     * Christian. Other faith paths return an empty array.
     *
     * @return array<int, array{reference: string, text: string}>
     */
    public function forTopic(string $topic, ?string $faithPath = null): array
    {
        if (! $this->isChristian($faithPath)) {
            return [];
        }

        $t = mb_strtolower($topic);

        if ($this->containsAny($t, ['gratitude', 'thank', 'appreciat'])) {
            return [
                ['reference' => '1 Thessalonians 5:18', 'text' => 'In every thing give thanks.'],
                ['reference' => 'Psalm 107:1', 'text' => 'O give thanks unto the LORD, for he is good.'],
                ['reference' => 'Colossians 3:17', 'text' => 'Do all in the name of the Lord Jesus, giving thanks.'],
                ['reference' => 'Psalm 100:4', 'text' => 'Enter into his gates with thanksgiving.'],
            ];
        }

        if ($this->containsAny($t, ['patience', 'waiting', 'persever', 'endur'])) {
            return [
                ['reference' => 'Romans 12:12', 'text' => 'Patient in tribulation; continuing instant in prayer.'],
                ['reference' => 'James 1:4', 'text' => 'Let patience have her perfect work.'],
                ['reference' => 'Galatians 6:9', 'text' => 'In due season we shall reap, if we faint not.'],
                ['reference' => 'Isaiah 40:31', 'text' => 'They that wait upon the LORD shall renew their strength.'],
            ];
        }

        if ($this->containsAny($t, ['anxiet', 'anxious', 'worry', 'fear', 'afraid', 'stress', 'trouble'])) {
            return [
                ['reference' => 'Philippians 4:6', 'text' => 'Be careful for nothing; but in every thing by prayer.'],
                ['reference' => '1 Peter 5:7', 'text' => 'Casting all your care upon him; for he careth for you.'],
                ['reference' => 'Isaiah 41:10', 'text' => 'Fear thou not; for I am with thee.'],
                ['reference' => 'John 14:27', 'text' => 'Let not your heart be troubled, neither let it be afraid.'],
                ['reference' => 'Psalm 56:3', 'text' => 'What time I am afraid, I will trust in thee.'],
            ];
        }

        if ($this->containsAny($t, ['strength', 'courage', 'brave', 'weak', 'tired', 'weary'])) {
            return [
                ['reference' => 'Philippians 4:13', 'text' => 'I can do all things through Christ which strengtheneth me.'],
                ['reference' => 'Isaiah 40:31', 'text' => 'They that wait upon the LORD shall renew their strength.'],
                ['reference' => 'Joshua 1:9', 'text' => 'Be strong and of a good courage.'],
                ['reference' => 'Psalm 46:1', 'text' => 'God is our refuge and strength.'],
            ];
        }

        if ($this->containsAny($t, ['love', 'kindness', 'compassion'])) {
            return [
                ['reference' => '1 Corinthians 13:4', 'text' => 'Charity suffereth long, and is kind.'],
                ['reference' => '1 John 4:8', 'text' => 'God is love.'],
                ['reference' => 'John 15:12', 'text' => 'Love one another, as I have loved you.'],
                ['reference' => 'Colossians 3:14', 'text' => 'Above all these things put on charity.'],
            ];
        }

        if ($this->containsAny($t, ['forgiv', 'mercy', 'grace', 'repent', 'sorry'])) {
            return [
                ['reference' => '1 John 1:9', 'text' => 'He is faithful and just to forgive us our sins.'],
                ['reference' => 'Ephesians 4:32', 'text' => 'Forgiving one another, even as God hath forgiven you.'],
                ['reference' => 'Psalm 103:12', 'text' => 'As far as the east is from the west, hath he removed our sins.'],
                ['reference' => 'Micah 7:18', 'text' => 'He delighteth in mercy.'],
            ];
        }

        if ($this->containsAny($t, ['hope', 'future', 'despair', 'discourag'])) {
            return [
                ['reference' => 'Jeremiah 29:11', 'text' => 'Thoughts of peace, and not of evil, to give you an expected end.'],
                ['reference' => 'Romans 15:13', 'text' => 'The God of hope fill you with all joy and peace.'],
                ['reference' => 'Psalm 42:11', 'text' => 'Hope thou in God.'],
                ['reference' => 'Hebrews 11:1', 'text' => 'Faith is the substance of things hoped for.'],
            ];
        }

        if ($this->containsAny($t, ['peace', 'rest', 'calm', 'still', 'quiet', 'sabbath'])) {
            return [
                ['reference' => 'John 14:27', 'text' => 'Peace I leave with you, my peace I give unto you.'],
                ['reference' => 'Philippians 4:7', 'text' => 'The peace of God, which passeth all understanding.'],
                ['reference' => 'Matthew 11:28', 'text' => 'Come unto me, and I will give you rest.'],
                ['reference' => 'Psalm 23:2', 'text' => 'He leadeth me beside the still waters.'],
            ];
        }

        if ($this->containsAny($t, ['faith', 'trust', 'believe', 'doubt'])) {
            return [
                ['reference' => 'Hebrews 11:1', 'text' => 'Faith is the substance of things hoped for.'],
                ['reference' => 'Proverbs 3:5', 'text' => 'Trust in the LORD with all thine heart.'],
                ['reference' => 'Mark 11:24', 'text' => 'What things soever ye desire, when ye pray, believe.'],
                ['reference' => 'Romans 10:17', 'text' => 'Faith cometh by hearing, and hearing by the word of God.'],
            ];
        }

        if ($this->containsAny($t, ['heal', 'comfort', 'grief', 'loss', 'mourn', 'sick'])) {
            return [
                ['reference' => 'Psalm 34:18', 'text' => 'The LORD is nigh unto them that are of a broken heart.'],
                ['reference' => 'Matthew 5:4', 'text' => 'Blessed are they that mourn: for they shall be comforted.'],
                ['reference' => '2 Corinthians 1:4', 'text' => 'Who comforteth us in all our tribulation.'],
                ['reference' => 'Psalm 147:3', 'text' => 'He healeth the broken in heart, and bindeth up their wounds.'],
            ];
        }

        if ($this->containsAny($t, ['wisdom', 'guid', 'decis', 'direction', 'understand', 'discern'])) {
            return [
                ['reference' => 'James 1:5', 'text' => 'If any of you lack wisdom, let him ask of God.'],
                ['reference' => 'Proverbs 3:5-6', 'text' => 'Trust in the LORD; and he shall direct thy paths.'],
                ['reference' => 'Psalm 119:105', 'text' => 'Thy word is a lamp unto my feet, and a light unto my path.'],
                ['reference' => 'Isaiah 30:21', 'text' => 'This is the way, walk ye in it.'],
            ];
        }

        if ($this->containsAny($t, ['joy', 'rejoic', 'happy', 'glad'])) {
            return [
                ['reference' => 'Philippians 4:4', 'text' => 'Rejoice in the Lord alway.'],
                ['reference' => 'Nehemiah 8:10', 'text' => 'The joy of the LORD is your strength.'],
                ['reference' => 'Psalm 118:24', 'text' => 'This is the day which the LORD hath made; we will rejoice.'],
                ['reference' => 'Romans 12:12', 'text' => 'Rejoicing in hope; patient in tribulation.'],
            ];
        }

        if ($this->containsAny($t, ['prayer', 'pray', 'meditat', 'worship', 'fast', 'devotion'])) {
            return [
                ['reference' => '1 Thessalonians 5:17', 'text' => 'Pray without ceasing.'],
                ['reference' => 'Matthew 6:6', 'text' => 'Pray to thy Father which is in secret.'],
                ['reference' => 'Psalm 1:2', 'text' => 'In his law doth he meditate day and night.'],
                ['reference' => 'John 4:24', 'text' => 'Worship him in spirit and in truth.'],
            ];
        }

        return [
            ['reference' => 'Jeremiah 29:11', 'text' => 'Thoughts of peace, to give you an expected end.'],
            ['reference' => 'Philippians 4:13', 'text' => 'I can do all things through Christ which strengtheneth me.'],
            ['reference' => 'Romans 8:28', 'text' => 'All things work together for good to them that love God.'],
            ['reference' => 'Psalm 23:1', 'text' => 'The LORD is my shepherd; I shall not want.'],
            ['reference' => 'Proverbs 3:5', 'text' => 'Trust in the LORD with all thine heart.'],
        ];
    }

    public function formattedText(string $topic, ?string $faithPath = null): string
    {
        $refs = $this->forTopic($topic, $faithPath);

        return collect($refs)
            ->map(fn ($ref) => $ref['reference'].' - '.$ref['text'])
            ->implode("\n");
    }

    private function isChristian(?string $faithPath): bool
    {
        return str_contains(mb_strtolower((string) $faithPath), 'christ');
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
