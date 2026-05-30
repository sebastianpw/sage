<?php
// public/view_gen_expressions2_sql.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$message = '';
$error = '';
$sql = '';

$expressions = [
    // Volume 8: Power & Intimidation
    ['name' => 'The "Menacing" Aura (JoJo Effect)', 'description' => 'Raw intimidation made visible. Facial Visual: Face darkened with heavy shadows. Eyes glow white or colored (red/gold). Sharp predatory grin or emotionless glare. Visual Effects: Dark wavy vertical lines radiating from body. Sometimes kanji characters float. Body Posture: Standing still but radiating threat. Shoulders squared. Hands in pockets or arms crossed. Context: Powerful character revealing true strength, entering battle mode, or warning to back off. Archetype: The Final Boss, The Awakened Hero, The Demon King.'],
    ['name' => 'The "Vein Pop" (Stress Indicator)', 'description' => 'Visual shorthand for mounting anger or effort. Facial Visual: Prominent cross-shaped or pulsing vein mark appears on temple or forehead. Face may be otherwise calm or twitching with forced smile. Body Posture: Rigid tension. Fists clenching and unclenching. Maintaining forced composure. Context: Being annoyed by an idiot, holding back rage, or straining under physical load. Archetype: The Short-Tempered Leader, The Strict Teacher, The Exhausted Parent Figure.'],
    ['name' => 'The "Eye Gleam" (Calculated Malice)', 'description' => 'The flash before the strike. Facial Visual: Sharp white or colored light reflection appears across eyes, often with glasses glare (glasses become completely white, hiding eyes). Mouth curves into knowing smirk. Body Posture: Head tilted down slightly, looking up through brow. Fingers steepled or adjusting glasses. Context: Strategist executing plan, villain\'s trap activating, or revealing crucial information. Archetype: The Chessmaster, The Manipulator, The Genius.'],
    ['name' => 'The "Dragon Eyes" (Beast Awakening)', 'description' => 'When humanity slips away. Facial Visual: Pupils become vertical slits (reptilian). Iris glows or changes to unnatural color. Teeth may sharpen visibly. Facial features become slightly more angular/feral. Body Posture: Hunched forward predatorily. Fingers curled like claws. Breathing becomes visible vapor. Context: Berserker mode activation, demonic possession, or channeling primal power. Archetype: The Were-creature, The Possessed, The Beast Within.'],
    ['name' => 'The "Killing Intent" Chill', 'description' => 'The supernatural weight of murderous will. Facial Visual: Face goes completely blank and dead. Eyes become black voids or thin slits. Entire color palette shifts to cold blues/grays. Visual Effects: Victim shown frozen with ice crystals or cold breath cloud. Perspective warps to show looming threat. Body Posture: Killer shown from low angle, towering. Victim is small, trembling, sweating despite cold. Context: Master swordsman facing novice, assassin revealing themselves, or moment before execution. Archetype: The Assassin, The Monster, The Veteran Warrior.'],

    // Volume 9: Cute & Moe
    ['name' => 'The "Puppy Eyes" (Irresistible Plea)', 'description' => 'Maximum cuteness weaponized. Facial Visual: Eyes enormous and glistening with unshed tears (not crying, just watery). Lower lip trembles or pouts slightly. Eyebrows raised in center (worried/pleading). Often includes light blush. Body Posture: Hands clasped in front of chest or holding edge of someone\'s sleeve. Slightly hunched, making self smaller. Head tilted down but eyes looking up. Context: Begging for food/money/help, trying to avoid punishment, or genuine desperate plea. Archetype: The Cute Mascot, The Little Sister, The Beggar.'],
    ['name' => 'The "Nya" (Cat Gesture)', 'description' => 'Feline playfulness. Facial Visual: One eye closed in wink, other wide and bright. Mouth is classic cat mouth (w or :3 shape). Tongue might stick out slightly. Small fang visible. Body Posture: One or both hands raised to head height with fingers curled to mimic cat paws. Slight lean or bounce. Sometimes includes head tilt. Context: Playful teasing, being cute on purpose, or literal cat-person behavior. Archetype: The Catgirl/boy, The Playful Trickster, The Idol.'],
    ['name' => 'The "Fumble" Blush (Clumsy Charm)', 'description' => 'Embarrassment from one\'s own mistake. Facial Visual: Eyes are spirals or closed in distress. Entire face is red (not just cheeks). Mouth is wavy line of dismay. Small sweat drops on temples. Body Posture: Hands waving frantically in front of body (defensive gesture). Bowing apologetically or hiding face in hands. Papers/objects scattered around from fumble. Context: Dropping important documents, tripping in front of crush, or saying something stupid. Archetype: The Klutz, The Nervous Rookie, The Awkward Genius.'],
    ['name' => 'The "Headpat Receive" (Comfort Acceptance)', 'description' => 'Accepting affection/praise. Facial Visual: Eyes closed in contentment, eyebrows relaxed. Mouth is small gentle smile. Soft blush on cheeks. Face tilted slightly into the hand. Body Posture: Body relaxed, shoulders dropped. Might lean into touch. Sometimes hands clasped at chest. Context: Being comforted after hardship, receiving genuine praise, or bonding moment. Archetype: The Wounded Hero, The Child, The Loyal Companion.'],
    ['name' => 'The "Awawawa" Panic (Adorable Fluster)', 'description' => 'Overwhelmed by multiple problems at once. Facial Visual: Eyes are swirls or X\'s. Mouth wide open with visible panic. Sweat drops flying everywhere. Face may be partially shadowed despite chaos. Body Posture: Arms flailing wildly in all directions. Running in place or spinning. Objects (papers, tools, creatures) orbiting around them in chaos. Context: Multiple alarms going off, too many tasks, or being swarmed by admirers. Archetype: The Overworked Administrator, The Popular Student, The Overwhelmed Parent.'],

    // Volume 10: Cool & Stoic
    ['name' => 'The "Tch" Click (Dismissive Annoyance)', 'description' => 'Too cool to engage fully. Facial Visual: Eyes looking to far side, not making contact. One eyebrow slightly raised. Mouth is small frown or flat line. Minimal expression. Body Posture: Head turned away, showing profile. Hand in pocket or adjusting collar/cuff. Walking away or leaning against wall. Context: Dismissing inferior opponent, hiding concern behind irritation, or maintaining cool facade. Archetype: The Rival, The Lone Wolf, The Tsundere.'],
    ['name' => 'The "Hmph" Huff (Proud Rejection)', 'description' => 'Aristocratic dismissal. Facial Visual: Eyes closed with eyebrows raised in disdain. Head turned sharply to side. Nose pointed upward. Small smirk or neutral mouth. Body Posture: Arms crossed high on chest. Back straight. Sometimes includes hair flip or fan snap. Context: Rejecting an offer, dismissing a commoner, or maintaining noble pride. Archetype: The Noble, The Ojou-sama, The Prideful Rival.'],
    ['name' => 'The "Glasses Adjust" (Calculation Mode)', 'description' => 'The thinking pose. Facial Visual: Eyes hidden behind light reflection on glasses (white lenses). Small knowing smile or completely neutral expression. Body Posture: One hand adjusting glasses by bridge (classic two-finger push) or by temple. Other hand often on hip or holding clipboard. Context: About to explain something complex, formulating a plan, or hiding emotions behind intellectual facade. Archetype: The Strategist, The Scientist, The Manipulator.'],
    ['name' => 'The "Hair Shadow" (Mysterious Bishounen)', 'description' => 'The cool character\'s natural state. Facial Visual: One or both eyes partially obscured by hanging hair. Visible eye is calm and half-lidded. Face is angular and composed. Body Posture: Relaxed but elegant posture. Often shown in profile or three-quarter view. Minimal movement. Context: The mysterious transfer student, the quiet observer, or tragic backstory holder. Archetype: The Mysterious Stranger, The Tragic Hero, The Lone Wolf.'],
    ['name' => 'The "Thousand Yard Stare" (Seen Too Much)', 'description' => 'The weight of experience. Facial Visual: Eyes are dull and unfocused, looking at nothing/everything. No highlight in pupils. Face neutral but somehow heavy. Slight bags under eyes. Body Posture: Completely still. Often smoking or holding a drink. Sitting slumped or standing at window. Context: Post-battle reflection, recalling trauma, or weariness of veterans. Archetype: The War Veteran, The Survivor, The Jaded Detective.'],

    // Volume 11: Transformation & Revelation
    ['name' => 'The "Hair Rise" (Power Surge)', 'description' => 'Energy manifesting physically. Visual: Hair defies gravity, floating upward or whipping dramatically. Individual strands detailed and dynamic. Sometimes changes color or glows at tips. Visual Effects: Wind or energy particles swirl upward. Clothing also billows. Ground may crack beneath feet. Body Posture: Arms slightly away from body. Head tilted back. Fists clenching. Aura radiates outward. Context: Powering up, transformation sequence, or breaking through limits. Archetype: The Shonen Hero, The Super Saiyan, The Magical Girl.'],
    ['name' => 'The "Lens Flare" Eye (Divine/Demonic)', 'description' => 'Supernatural power activation. Facial Visual: One or both eyes emit bright light (often with star/cross flare effect). Iris may show unique symbol or pattern. Rest of face shadowed by contrast. Body Posture: Head slightly lowered, looking up at target. Often shown from low angle to emphasize power. Context: Special ability activation, revealing true form, or divine/demonic intervention. Archetype: The Chosen One, The Deity, The Demon.'],
    ['name' => 'The "Mask Crack" (Breaking Point)', 'description' => 'The facade shattering. Visual: Literal or metaphorical cracks appear across character\'s face, emanating from one eye or mouth. Cracks glow or leak darkness/light. Facial Visual: Expression beneath is often opposite of what was shown before (calm to rage, or smile to despair). Body Posture: Hand reaching up to face. Body trembling. Posture breaking down. Context: Emotional breakdown, revealing true self, or corruption spreading. Archetype: The Fake Hero, The Possessed, The Broken Villain.'],
    ['name' => 'The "Silhouette Reversal" (Identity Reveal)', 'description' => 'The dramatic unmasking. Visual: Character shown only in black silhouette initially. Then light flares from behind/around them, revealing features in high contrast. Often accompanied by cloth/cloak whipping away. Facial Visual: Face emerges from shadow with dramatic lighting—often split lighting (half light/half shadow). Body Posture: Stepping forward into light, or turning to face camera. Dramatic arm gesture (removing mask/hood). Context: Mysterious helper revealing themselves, villain\'s true identity, or hero\'s return. Archetype: The Masked Vigilante, The Secret Identity, The Traitor.'],
    ['name' => 'The "Reflection Lie" (Duality)', 'description' => 'The inner self vs outer self. Visual: Character\'s reflection in mirror/water/glass shows different expression or even different form (demonic, monstrous, or idealized). Facial Visual: Real face is neutral or smiling. Reflection shows true emotion (crying, rage, fear) or true nature. Body Posture: Standing still, looking at or deliberately avoiding the reflection. Context: Internal conflict, hiding true feelings, or foreshadowing transformation. Archetype: The Double Agent, The Cursed, The Split Personality.'],

    // Volume 12: Environmental Reactions
    ['name' => 'The "Wind Whip" (Dramatic Tension)', 'description' => 'Nature emphasizing the moment. Visual: Strong wind suddenly blows through scene. Hair and clothing stream horizontally. Leaves, petals, or papers scatter dramatically. Facial Visual: Eyes narrowed against wind or closed. Expression is serious or determined. Hair covering part of face. Body Posture: Leaning into wind or standing firm against it. Coat/cloak billowing behind dramatically. Context: Tense standoff, moment of decision, or arrival of important character. Archetype: The Rival\'s Entrance, The Challenge Accepted, The Wanderer.'],
    ['name' => 'The "Rainfall Acceptance" (Melancholy)', 'description' => 'Letting the rain hide tears. Visual: Character standing in rain, head tilted back or down. Rain streams down face, mixing with or hiding tears. Facial Visual: Eyes closed or open looking at sky. Expression neutral or slightly pained. We can\'t tell if they\'re crying. Body Posture: Arms hanging limp at sides or raised to sky. Standing still while others run for cover. Context: Loss, acceptance of fate, emotional release, or finding peace. Archetype: The Mourner, The Defeated, The Reborn.'],
    ['name' => 'The "Sunset Silhouette" (Nostalgia)', 'description' => 'The golden hour of memory. Visual: Character(s) shown in dark silhouette against orange/red sunset sky. High contrast. Often includes long shadows. Facial Visual: Details are minimal—just the outline. Sometimes a small smile visible on the edge. Body Posture: Relaxed, often sitting or standing side-by-side with companions. Peaceful poses. Context: End of adventure, childhood memory, or bittersweet goodbye. Archetype: The Friend Group, The Memory, The Final Scene.'],
    ['name' => 'The "Chibigaki" (Comedic Background)', 'description' => 'Environmental chaos reaction. Visual: Character drawn in super-deformed (SD/chibi) style in background while others continue normally in foreground. Often includes exaggerated action (being tossed around, spinning, flailing). Facial Visual: Simple dot eyes, simple mouth. Pure comedic expression. Body Posture: Being buffeted by wind, waves, explosions. Tumbling or ragdolling. Context: Character experiencing minor disaster while others are oblivious, comedic punishment, or physical comedy. Archetype: The Comic Relief, The Unlucky, The Butt of the Joke.'],
    ['name' => 'The "Spotlight Stand" (Main Character Energy)', 'description' => 'Reality acknowledging the protagonist. Visual: Shaft of light (from window, break in clouds, or seemingly nowhere) illuminates only the character while surroundings remain normal or dim. Facial Visual: Face lit from above/front with heroic lighting. Expression confident or determined. Body Posture: Standing tall. Often with hand extended, weapon raised, or making a declaration. Context: Making a stand, giving a speech, or plot armor manifesting visually. Archetype: The Protagonist, The Hero\'s Moment, The Chosen One.'],
];

function esc_sql_value($v) {
    if (is_null($v)) return 'NULL';
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $v) . "'";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $frameId = (int)($_POST['img2img_frame_id'] ?? 0);
    if ($frameId <= 0) {
        $error = "Please provide a valid img2img Frame ID (positive integer).";
    } else {
        $now = date('Y-m-d H:i:s');
        $stmts = [];
        foreach ($expressions as $expr) {
            $name = $expr['name'];
            $desc = $expr['description'];

            // NOTE: active_map_run_id removed from columns
            $cols = "`name`, `order`, `description`, `prompt_negative`, `seed`, `created_at`, `updated_at`, `regenerate_images`, `state_id_active`, `img2img`, `img2img_frame_id`, `img2img_frame_filename`, `img2img_prompt`, `cnmap`, `cnmap_frame_id`, `cnmap_frame_filename`, `cnmap_prompt`";

            $vals = [
                esc_sql_value($name),           // name
                '0',                           // order
                esc_sql_value($desc),          // description
                'NULL',                        // prompt_negative
                'NULL',                        // seed
                esc_sql_value($now),           // created_at
                esc_sql_value($now),           // updated_at
                '0',                           // regenerate_images
                'NULL',                        // state_id_active
                '1',                           // img2img
                (int)$frameId,                 // img2img_frame_id <-- user provided
                'NULL',                        // img2img_frame_filename
                'NULL',                        // img2img_prompt
                '0',                           // cnmap
                'NULL',                        // cnmap_frame_id
                'NULL',                        // cnmap_frame_filename
                'NULL'                         // cnmap_prompt
            ];

            $stmts[] = "INSERT INTO `generatives` ($cols) VALUES (" . implode(", ", $vals) . ");";
        }

        $sql = implode("\n", $stmts);
        $message = "Generated " . count($stmts) . " INSERT statements using img2img_frame_id = $frameId.";
        if (!empty($_POST['download']) && $_POST['download'] === '1') {
            $filename = "generatives_expressions2_{$frameId}_" . date('Ymd_His') . ".sql";
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $sql;
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generate Expressions 2 SQL</title>
<link rel="stylesheet" href="/css/base.css">
<style>
    body { padding: 20px; max-width: 900px; margin: 0 auto; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
    .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; margin-bottom: 10px; }
    .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 10px; }
    textarea { width: 100%; height: 420px; font-family: monospace; font-size: 13px; margin-top: 8px; }
    .form-row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
    .form-group { flex: 1 1 240px; }
    .btn { padding: 8px 14px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.08); background: #f3f3f3; cursor:pointer; }
    .btn-primary { background:#007bff;color:#fff;border-color:#007bff; }
</style>
</head>
<body>
    <h1>Generate `generatives` INSERTs (Expressions 2 - Volumes 8-12)</h1>
    <p class="text-muted">Provide an <strong>img2img frame id</strong> and the view will create one INSERT per expression using <code>img2img = 1</code> and that frame id. (No <code>active_map_run_id</code> column will be included.)</p>

    <?php if ($message): ?>
        <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="genForm" style="margin-bottom:12px;">
        <div class="form-row">
            <div class="form-group">
                <label>img2img Frame ID</label>
                <input type="number" name="img2img_frame_id" class="form-control" placeholder="e.g. 14917" value="<?= isset($_POST['img2img_frame_id']) ? (int)$_POST['img2img_frame_id'] : '' ?>" required>
            </div>

            <div style="display:flex; gap:8px; align-items:center;">
                <button type="submit" class="btn btn-primary">Generate SQL (Preview)</button>
                <button type="submit" name="download" value="1" class="btn">Generate &amp; Download SQL</button>
            </div>
        </div>
    </form>

    <?php if ($sql): ?>
        <label>SQL Preview</label>
        <textarea id="sqlPreview" readonly><?php echo htmlspecialchars($sql); ?></textarea>
        <div style="margin-top:8px; display:flex; gap:8px;">
            <button class="btn" id="copyBtn">Copy to clipboard</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="img2img_frame_id" value="<?= (int)($_POST['img2img_frame_id'] ?? 0) ?>">
                <input type="hidden" name="download" value="1">
                <button type="submit" class="btn">Download SQL</button>
            </form>
        </div>
        <p id="copyMsg" style="margin-top:8px;color:#2d3748;display:none;">Copied to clipboard ✔</p>
        <script>
            document.getElementById('copyBtn').addEventListener('click', function(){
                var ta = document.getElementById('sqlPreview');
                var text = ta.value;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function(){ 
                        document.getElementById('copyMsg').style.display = 'block';
                        setTimeout(function(){ document.getElementById('copyMsg').style.display='none'; }, 2000);
                    }, function(){ fallbackCopy(); });
                } else {
                    fallbackCopy();
                }
                function fallbackCopy(){
                    ta.select();
                    try {
                        document.execCommand('copy');
                        document.getElementById('copyMsg').style.display = 'block';
                        setTimeout(function(){ document.getElementById('copyMsg').style.display='none'; }, 2000);
                    } catch (e) {
                        alert('Copy failed — please select and copy manually.');
                    }
                }
            });
        </script>
    <?php endif; ?>

    <p style="margin-top:18px;"><a href="/view_video_admin.php" class="btn">Back to Video Admin</a></p>
</body>
</html>