<?php

namespace App\Services\V2\Website;

use App\Services\V2\SystemSettingService;
use Illuminate\Support\Str;

/*
 * W121 MIXXTUNE_WEBSITE_HOMEPAGE
 * The words on the homepage and the questions of the Join form.
 * Kept as one JSON value in system_settings (key website.homepage).
 * Super Admin edits it in Website -> Homepage; the homepage reads it.
 * Anything missing or broken falls back to the defaults below, so the
 * homepage always renders.
 */
class HomepageContent
{
    public const KEY = 'website.homepage';

    public const TYPES = ['text', 'textarea', 'select', 'phone', 'email', 'number', 'date'];

    /* name and phone are needed to call the person back: always shown and required */
    public const LOCKED = ['name' => 'text', 'phone' => 'phone'];

    public const CORE = ['name', 'phone', 'email', 'role', 'brand', 'city', 'message'];

    public const SECTIONS = ['stores', 'how', 'features', 'royalty', 'audience', 'cta'];

    public const MAX_FIELDS = 20;

    public function __construct(private SystemSettingService $settings)
    {
    }

    public static function defaults(): array
    {
        return [
            'hero' => [
                'eyebrow' => 'Music distribution for labels & artists',
                'title' => "Deliver your music\ninto every orbit.",
                'text' => 'Mixx Tune delivers your releases to streaming stores around the world, shows you every report, and pays your royalties straight to your bank.',
                'join_button' => 'Join Mixx Tune',
                'second_button' => 'See how it works',
            ],
            'stores' => ['Spotify', 'JioSaavn', 'Apple Music', 'YouTube Music', 'Amazon Music', 'Wynk', 'Gaana', 'Instagram', 'Facebook', 'Deezer', 'TikTok', 'Tidal'],
            'sections' => array_fill_keys(self::SECTIONS, true),
            'contact' => ['email' => '', 'phone' => '', 'address' => ''],
            'join' => [
                'title' => 'Join Mixx Tune',
                'sub' => 'Leave your details. Our team will call you and discuss everything with you.',
                'button' => 'Send join request',
                'thanks' => 'Thank you. Our team will call you on the number you gave and talk it through with you.',
                'notify_bell' => true,
                'notify_email' => true,
                'fields' => [
                    ['id' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'show' => true, 'options' => [], 'hint' => ''],
                    ['id' => 'phone', 'label' => 'Phone / WhatsApp', 'type' => 'phone', 'required' => true, 'show' => true, 'options' => [], 'hint' => ''],
                    ['id' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'show' => true, 'options' => [], 'hint' => ''],
                    ['id' => 'role', 'label' => 'I am joining as', 'type' => 'select', 'required' => true, 'show' => true, 'options' => ['Music label', 'Artist', 'Manager', 'Other'], 'hint' => ''],
                    ['id' => 'brand', 'label' => 'Label or artist name', 'type' => 'text', 'required' => false, 'show' => true, 'options' => [], 'hint' => ''],
                    ['id' => 'city', 'label' => 'City / State', 'type' => 'text', 'required' => false, 'show' => true, 'options' => [], 'hint' => ''],
                    ['id' => 'message', 'label' => 'Tell us about your music', 'type' => 'textarea', 'required' => false, 'show' => true, 'options' => [], 'hint' => ''],
                ],
            ],
        ];
    }

    /* the published content, always complete and clean */
    public function get(): array
    {
        $saved = $this->settings->get(self::KEY);

        return $this->normalize(is_array($saved) ? $saved : []);
    }

    /* clean the editor's content and publish it; returns the stored value */
    public function publish(array $input, ?int $userId): array
    {
        $clean = $this->normalize($input);
        $this->settings->set(self::KEY, $clean, 'website', 'json', $userId);

        return $clean;
    }

    public function normalize(array $in): array
    {
        $d = self::defaults();

        $hero = [];
        foreach ($d['hero'] as $k => $default) {
            $max = match ($k) { 'text' => 400, 'title' => 120, 'eyebrow' => 120, default => 40 };
            $hero[$k] = $this->text(data_get($in, "hero.$k"), $max, $default, $k === 'title');
        }

        $stores = data_get($in, 'stores');
        if (is_string($stores)) {
            $stores = explode(',', $stores);
        }
        $stores = is_array($stores) ? $stores : $d['stores'];
        $stores = array_values(array_unique(array_filter(array_map(fn ($s) => is_scalar($s) ? $this->line((string) $s, 40) : '', $stores), fn ($s) => $s !== '')));
        $stores = array_slice($stores, 0, 20);
        if ($stores === []) {
            $stores = $d['stores'];
        }

        $sections = [];
        foreach (self::SECTIONS as $s) {
            $v = data_get($in, "sections.$s");
            $sections[$s] = $v === null ? true : filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }

        $contact = [
            'email' => $this->text(data_get($in, 'contact.email'), 190, ''),
            'phone' => $this->text(data_get($in, 'contact.phone'), 40, ''),
            'address' => $this->text(data_get($in, 'contact.address'), 300, ''),
        ];
        if ($contact['email'] !== '' && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $contact['email'] = '';
        }

        $join = [
            'title' => $this->text(data_get($in, 'join.title'), 80, $d['join']['title']),
            'sub' => $this->text(data_get($in, 'join.sub'), 300, $d['join']['sub']),
            'button' => $this->text(data_get($in, 'join.button'), 40, $d['join']['button']),
            'thanks' => $this->text(data_get($in, 'join.thanks'), 300, $d['join']['thanks']),
            'notify_bell' => data_get($in, 'join.notify_bell') === null ? true : filter_var(data_get($in, 'join.notify_bell'), FILTER_VALIDATE_BOOLEAN),
            'notify_email' => data_get($in, 'join.notify_email') === null ? true : filter_var(data_get($in, 'join.notify_email'), FILTER_VALIDATE_BOOLEAN),
            'fields' => $this->fields(data_get($in, 'join.fields')),
        ];

        return ['hero' => $hero, 'stores' => $stores, 'sections' => $sections, 'contact' => $contact, 'join' => $join];
    }

    private function fields(mixed $list): array
    {
        $defaults = array_column(self::defaults()['join']['fields'], null, 'id');
        if (!is_array($list) || $list === []) {
            return array_values($defaults);
        }

        $out = [];
        $seen = [];
        foreach ($list as $f) {
            if (!is_array($f) || count($out) >= self::MAX_FIELDS) {
                continue;
            }
            $id = is_string($f['id'] ?? null) ? $f['id'] : '';
            if (!in_array($id, self::CORE, true) && !preg_match('/^q_[a-z0-9]{3,16}$/', $id)) {
                $id = 'q_'.Str::lower(Str::random(8));
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $type = in_array($f['type'] ?? null, self::TYPES, true) ? $f['type'] : 'text';
            $options = $f['options'] ?? [];
            if (is_string($options)) {
                $options = explode(',', $options);
            }
            $options = is_array($options) ? $options : [];
            $options = array_slice(array_values(array_unique(array_filter(array_map(fn ($o) => is_scalar($o) ? $this->line((string) $o, 80) : '', $options), fn ($o) => $o !== ''))), 0, 30);

            $field = [
                'id' => $id,
                'label' => $this->text($f['label'] ?? null, 120, $defaults[$id]['label'] ?? 'Question'),
                'type' => $type,
                'required' => filter_var($f['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'show' => filter_var($f['show'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'options' => $options,
                'hint' => $this->text($f['hint'] ?? null, 120, ''),
            ];
            if (isset(self::LOCKED[$id])) {
                $field['type'] = self::LOCKED[$id];
                $field['required'] = true;
                $field['show'] = true;
            }
            if ($field['type'] === 'select' && $field['options'] === []) {
                $field['type'] = 'text';
            }
            $out[] = $field;
        }

        /* name and phone can never be removed */
        foreach (array_reverse(array_keys(self::LOCKED)) as $id) {
            if (!isset($seen[$id])) {
                array_unshift($out, $defaults[$id]);
            }
        }

        return $out;
    }

    /** validation rules for the visible questions of the published form */
    public function rules(array $content): array
    {
        $rules = [];
        foreach ($content['join']['fields'] as $f) {
            if (!$f['show']) {
                continue;
            }
            $r = [$f['required'] ? 'required' : 'nullable'];
            $r = array_merge($r, match ($f['type']) {
                'textarea' => ['string', 'max:2000'],
                'phone' => ['string', 'max:40', 'regex:/^[0-9+()\-\s]{6,20}$/'],
                'email' => ['string', 'email', 'max:190'],
                'number' => ['numeric', 'between:-999999999,999999999'],
                'date' => ['date'],
                'select' => ['string', \Illuminate\Validation\Rule::in($f['options'])],
                default => ['string', 'max:190'],
            });
            $rules['answers.'.$f['id']] = $r;
        }

        return $rules;
    }

    /** readable names for the validation messages ("The Full name field is required.") */
    public function attributes(array $content): array
    {
        $a = [];
        foreach ($content['join']['fields'] as $f) {
            $a['answers.'.$f['id']] = $f['label'];
        }

        return $a;
    }

    private function text(mixed $v, int $max, string $default, bool $multiline = false): string
    {
        if (!is_scalar($v)) {
            return $default;
        }
        $s = strip_tags((string) $v);
        $s = $multiline ? preg_replace("/[^\S\n]+/u", ' ', str_replace("\r", '', $s)) : $this->line($s, $max);
        $s = trim(preg_replace("/ *\n[ \n]*/", "\n", (string) $s));
        $s = mb_substr($s, 0, $max);

        return $s === '' ? $default : $s;
    }

    private function line(string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($s))), 0, $max);
    }
}
