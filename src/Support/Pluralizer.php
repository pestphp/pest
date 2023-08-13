<?php

declare(strict_types=1);

namespace Pest\Support;

final class Pluralizer
{
    /**
     * Plural word form rules.
     *
     * @var array<string>
     */
    public static array $plural = [
        '/(quiz)$/i' => '$1zes',
        '/^(ox)$/i' => '$1en',
        '/([m|l])ouse$/i' => '$1ice',
        '/(matr|vert|ind)ix$|ex$/i' => '$1ices',
        '/(stoma|epo|monar|matriar|patriar|oligar|eunu)ch$/i' => '$1chs',
        '/(x|ch|ss|sh)$/i' => '$1es',
        '/([^aeiouy]|qu)y$/i' => '$1ies',
        '/(hive)$/i' => '$1s',
        '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',
        '/(shea|lea|loa|thie)f$/i' => '$1ves',
        '/sis$/i' => 'ses',
        '/([ti])um$/i' => '$1a',
        '/(torped|embarg|tomat|potat|ech|her|vet)o$/i' => '$1oes',
        '/(bu)s$/i' => '$1ses',
        '/(alias)$/i' => '$1es',
        '/(fung)us$/i' => '$1i',
        '/(ax|test)is$/i' => '$1es',
        '/(us)$/i' => '$1es',
        '/s$/i' => 's',
        '/$/' => 's',
    ];

    /**
     * Singular word form rules.
     *
     * @var array<string>
     */
    public static array $singular = [
        '/(quiz)zes$/i' => '$1',
        '/(matr)ices$/i' => '$1ix',
        '/(vert|vort|ind)ices$/i' => '$1ex',
        '/^(ox)en$/i' => '$1',
        '/(alias)es$/i' => '$1',
        '/(octop|vir|fung)i$/i' => '$1us',
        '/(cris|ax|test)es$/i' => '$1is',
        '/(shoe)s$/i' => '$1',
        '/(o)es$/i' => '$1',
        '/(bus)es$/i' => '$1',
        '/([m|l])ice$/i' => '$1ouse',
        '/(x|ch|ss|sh)es$/i' => '$1',
        '/(m)ovies$/i' => '$1ovie',
        '/(s)eries$/i' => '$1eries',
        '/([^aeiouy]|qu)ies$/i' => '$1y',
        '/([lr])ves$/i' => '$1f',
        '/(tive)s$/i' => '$1',
        '/(hive)s$/i' => '$1',
        '/(li|wi|kni)ves$/i' => '$1fe',
        '/(shea|loa|lea|thie)ves$/i' => '$1f',
        '/(^analy)ses$/i' => '$1sis',
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1$2sis',
        '/([ti])a$/i' => '$1um',
        '/(n)ews$/i' => '$1ews',
        '/(h|bl)ouses$/i' => '$1ouse',
        '/(corpse)s$/i' => '$1',
        '/(gallows|headquarters)$/i' => '$1',
        '/(us)es$/i' => '$1',
        '/(us|ss)$/i' => '$1',
        '/s$/i' => '',
    ];

    /**
     * Irregular word forms.
     *
     * @var array<string>
     */
    public static array $irregular = [
        'child' => 'children',
        'corpus' => 'corpora',
        'criterion' => 'criteria',
        'foot' => 'feet',
        'freshman' => 'freshmen',
        'goose' => 'geese',
        'genus' => 'genera',
        'human' => 'humans',
        'man' => 'men',
        'move' => 'moves',
        'nucleus' => 'nuclei',
        'ovum' => 'ova',
        'person' => 'people',
        'phenomenon' => 'phenomena',
        'radius' => 'radii',
        'sex' => 'sexes',
        'stimulus' => 'stimuli',
        'syllabus' => 'syllabi',
        'tax' => 'taxes',
        'tech' => 'techs',
        'tooth' => 'teeth',
        'viscus' => 'viscera',
    ];

    /**
     * Uncountable word forms.
     *
     * @var array<string>
     */
    public static array $uncountable = [
        'audio',
        'bison',
        'chassis',
        'compensation',
        'coreopsis',
        'data',
        'deer',
        'education',
        'equipment',
        'fish',
        'gold',
        'information',
        'money',
        'moose',
        'offspring',
        'plankton',
        'police',
        'rice',
        'series',
        'sheep',
        'species',
        'swine',
        'traffic',
    ];

    /**
     * The cached copies of the plural inflections.
     *
     * @var array<string>
     */
    private static array $pluralCache = [];

    /**
     * The cached copies of the singular inflections.
     *
     * @var array<string>
     */
    private static array $singularCache = [];

    /**
     * @var string[]
     */
    private const FUNCTIONS = ['mb_strtolower', 'mb_strtoupper', 'ucfirst', 'ucwords'];

    /**
     * Get the singular form of the given word.
     */
    public static function singular(string $value): ?string
    {
        if (isset(Pluralizer::$singularCache[$value])) {
            return Pluralizer::$singularCache[$value];
        }

        $result = Pluralizer::inflect($value, Pluralizer::$singular, Pluralizer::$irregular);

        if (! is_null($result)) {
            Pluralizer::$singularCache[$value] = $result;
        }

        return $result ?? null;
    }

    /**
     * Get the plural form of the given word.
     */
    public static function plural(string $value, int $count = 2): ?string
    {
        if ($count == 1) {
            return $value;
        }

        if (in_array($value, Pluralizer::$irregular, true)) {
            return $value;
        }

        // First we'll check the cache of inflected values. We cache each word that
        // is inflected, so we don't have to spin through the regular expressions
        // on each subsequent method calls for this word by the app developer.
        if (isset(Pluralizer::$pluralCache[$value])) {
            return Pluralizer::$pluralCache[$value];
        }

        $irregular = array_flip(Pluralizer::$irregular);

        // When doing the singular to plural transformation, we'll flip the irregular
        // array since we need to swap sides on the keys and values. After we have
        // the transformed value we will cache it in memory for faster look-ups.
        $plural = Pluralizer::$plural;

        $result = Pluralizer::inflect($value, $plural, $irregular);

        if (! is_null($result)) {
            return Pluralizer::$pluralCache[$value] = $result;
        }

        return null;
    }

    /**
     * Perform auto inflection on an English word.
     *
     * @param  array<string>  $source
     * @param  array<string>  $irregular
     */
    private static function inflect(string $value, array $source, array $irregular): ?string
    {
        if (Pluralizer::uncountable($value)) {
            return $value;
        }

        // Next, we will check the "irregular" patterns which contain words that are
        // not easily summarized in regular expression rules, like "children" and
        // "teeth", both of which cannot get inflected using our typical rules.
        foreach ($irregular as $irregularKey => $pattern) {
            if (preg_match($pattern = '/'.$pattern.'$/i', $value) === 1) {
                $irregular = Pluralizer::matchCase($irregularKey, $value);

                return preg_replace($pattern, $irregular, $value);
            }
        }

        // Finally, we'll spin through the array of regular expressions and look for
        // matches for the word. If we find a match, we will cache and return the
        // transformed value, so we will quickly look it up on subsequent calls.
        foreach ($source as $patternKey => $inflected) {
            if (preg_match($patternKey, $value) === 1) {
                $inflected = preg_replace($patternKey, $inflected, $value);

                if (! is_null($inflected)) {
                    return Pluralizer::matchCase($inflected, $value);
                }
            }
        }

        return null;
    }

    /**
     * Determine if the given value is uncountable.
     */
    private static function uncountable(string $value): bool
    {
        return in_array(strtolower($value), Pluralizer::$uncountable, true);
    }

    /**
     * Attempt to match the case on two strings.
     */
    private static function matchCase(string $value, string $comparison): string
    {
        foreach (self::FUNCTIONS as $function) {
            if (call_user_func($function, $comparison) === $comparison) {
                return call_user_func($function, $value);
            }
        }

        return $value;
    }
}
