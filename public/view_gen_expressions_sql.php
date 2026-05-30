<?php
// public/view_generate_expressions_sql.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$message = '';
$error = '';
$sql = '';

$expressions = [
    // (same list as before) - shortened here but keep full list below in actual file...
    // Volume 1
    ['name' => 'The "Kitsune" Smile (The Closed-Eye Smile)', 'description' => 'The classic "Fox" expression. Friendly on the surface, unreadable underneath. Facial Visual: Eyes completely closed in upside-down U crescents. Mouth is a gentle, wide curve. Eyebrows relaxed or slightly raised. Body Posture: Head tilted slightly to the side. Shoulders relaxed. Hands often clasped behind the back or holding a cheek. Context: Genuine happiness, polite refusal, or masking a terrifying threat. Archetype: The Healer, The Manipulator, The Mentor.'],
    ['name' => 'The "Jito" Stare (The Flat Gaze)', 'description' => 'The half-lidded, unimpressed look of judgment. Facial Visual: Eyelids lowered halfway (dead fish eyes). Pupils visible but bored. Mouth is a straight horizontal line or a tiny dot. Body Posture: Slouching. Arms crossed loosely or hanging limp. Chin tucked in slightly. Context: Listening to an idiot, skepticism, or exhaustion with the situation. Archetype: The Tech Genius, The Tsundere, The Cynic.'],
    ['name' => 'The "Doya" Face (The Smug Gloat)', 'description' => 'The look of unearned confidence or victory. Facial Visual: Eyebrows arched high/inward. Eyes half-lidded but looking down at the viewer. Mouth curved into a V-shaped smirk (cat mouth) or a one-sided grin. Nose held high. Body Posture: Chest puffed out. Hands on hips or arms crossed. Head tilted back. Context: "I told you so", winning a game, or showing off a new weapon. Archetype: The Rival, The Brat, The Ace Pilot.' ],
    ['name' => 'The "Puku" Pout (The Balloon Cheeks)', 'description' => 'Childish frustration or demanding attention. Facial Visual: Cheeks physically puffed out with air (round shape). Eyes wide and staring upward. Mouth is small and puckered. Brows furrowed slightly but not angry. Body Posture: Shoulders hunched up high. Hands clenched into fists at the side or poking fingers together. Context: Being teased, not getting their way, or jealous affection. Archetype: The Childhood Friend, The Little Sister/Brother, The Spirit Companion.' ],
    ['name' => 'The "Hah?" (The Disgust Tilt)', 'description' => 'Looking at something like it\'s trash. Facial Visual: Head tilted back, looking down the nose. One eye slightly squinted, the other wide. Mouth curled in a sneer (showing upper teeth). Shadows often heavy under the chin. Body Posture: Leaning away from the subject. Hand waving "shoo" or resting on the back of the neck. Context: Encountering an enemy, seeing something gross, or pure arrogance. Archetype: The Villain, The Delinquent.' ],

    // Volume 2
    ['name' => 'The "Highlight Loss" (Dead Eyes)', 'description' => 'The moment the soul leaves the body. Facial Visual: Eyes are wide open, but the highlight is removed. Iris becomes a flat, dull color. A dark gradient shadow covers upper half of the eye. Body Posture: Perfectly still. Context: Trauma, mind control, entering a "berserk" state. Archetype: The Tragic Hero, The Sleeper Agent.'],
    ['name' => 'The "Pupil Tremor" (The Quake)', 'description' => 'The brain failing to process reality. Facial Visual: Eyes extremely wide. Iris/pupil drawn with a "wobbly" or jagged line. Tiny beads of sweat. Body Posture: Rigid tension; hands clutching head or chest. Context: Terror, extreme shock. Archetype: The Victim, The Rookie.'],
    ['name' => 'The "Shadowed Visage" (Gloom)', 'description' => 'Classic depression/rage visual. Facial Visual: Head tilted forward. Heavy dark gradient shadow covers the face from nose up. Eyes either invisible or glowing dots. Mouth a grim line. Body Posture: Slumped or standing ominously still. Context: Grief, suppressing emotional breakdown. Archetype: The Avenger, The Protagonist at their lowest.'],
    ['name' => 'The "Sanity Slippage" (The Dutch Smile)', 'description' => 'When pressure becomes too much and they snap. Facial Visual: One eye wide, other squinted. Eyebrows slanted opposite. Mouth smiling too wide, showing too many teeth. Head tilted at a Dutch Angle. Body Posture: Hand covering half the face or fingers digging into scalp. Context: Manic laughter, villainous breakdown. Archetype: The Corrupted Healer, The Mad Scientist.'],
    ['name' => 'The "Single Tear" (Silent Resignation)', 'description' => 'Accepting a sad fate with dignity. Facial Visual: Face neutral or gently smiling. Eyes soft. A single tear track from one eye. Body Posture: Standing tall. Head held high or looking at the sunset. Context: Saying goodbye, self-sacrifice. Archetype: The Martyr, The Veteran.'],

    // Volume 3
    ['name' => 'The "Tsundere" Flush (The Angry Blush)', 'description' => 'Defensive embarrassment. Heavy vertical blush lines across nose and cheeks. Eyebrows furrowed deeply. Mouth is open, shouting or stammering. Eyes often looking away. Body Posture: Arms crossed tightly or one finger pointing. Context: Being thanked, complimented. Archetype: The Rival, The Tough Mechanic.' ],
    ['name' => 'The "Doki" Soften (The Realization)', 'description' => 'The precise moment a character falls in love or realizes value. Facial Visual: Eyes widen slightly, pupils dilate. Mouth parts slightly. Soft diffuse blush. Body Posture: Body freezes mid-motion. Context: Sudden moment of intimacy. Archetype: The Protagonist, The Silent Type.' ],
    ['name' => 'The "Red-Ear" Turn (The Repressed Shy)', 'description' => 'Trying to maintain composure but failing. Facial Visual: Neutral face but ears bright red. Avoiding eye contact. Lips pressed. Body Posture: Stiff, robotic movements; adjusting glasses or hat. Context: A confession or accidental touch. Archetype: The Strategist, The Soldier.' ],
    ['name' => 'The "Sparkle" Gaze (Admiration)', 'description' => 'Seeing someone as a hero or idol. Eyes huge & shimmering; star glyphs in iris. Mouth small "O" or wide smile. Cheeks rosy. Body Posture: Leaning in close, hands clasped under chin. Context: Hero worship. Archetype: The Sidekick, The Spirit Companion.' ],
    ['name' => 'The "Steam" Whiteout (System Crash)', 'description' => 'Overwhelming embarrassment leading to brain death. Facial Visual: Eyes blank white circles. Face entirely red. Mouth a wavy line. Optional: steam clouds. Body Posture: Rigid or fainting. Context: Accidental perved situations, extreme compliments. Archetype: The Innocent.' ],

    // Volume 4
    ['name' => 'The "Grit" (Impact Absorption)', 'description' => 'Taking a hit or pushing limits. Facial Visual: Teeth clenched. One eye squeezed shut, other wide. Sweat flying. Body Posture: Head tucked down, shoulders hunched. Context: Blocking a heavy attack. Archetype: The Tank, The Shonen Hero.'],
    ['name' => 'The "Battle Roar" (Primal Scream)', 'description' => 'Releasing maximum energy. Facial Visual: Mouth wide open showing teeth and tongue. Eyes constricted to pinpoints. Heavy shading/lines under eyes. Body Posture: Head thrown back or lunging forward. Context: Final strike or berserker rage. Archetype: The Beast, The Berserker.' ],
    ['name' => 'The "Tunnel Vision" (The Sniper)', 'description' => 'Absolute cold focus. Facial Visual: Devoid of emotion; unblinking eyes. Body Posture: Absolute stillness. Context: Lining up a shot or hacking under fire. Archetype: The Sniper, The Hacker.' ],
    ['name' => 'The "Blood Wipe" (The Swagger)', 'description' => 'Confidence after violence. Facial Visual: Smirk or sharp grin; eyes half-lidded. A streak of dirt/blood on the cheek. Body Posture: Thumb wiping corner of mouth; shoulders relaxed. Context: Defeating a minion. Archetype: The Mercenary, The Villain.' ],
    ['name' => 'The "Hollow" Gasp (Exhaustion)', 'description' => 'The fight is over, adrenaline gone. Facial Visual: Mouth hanging open, panting. Eyes unfocused. Sweat dripping. Body Posture: Hands on knees or leaning. Context: Post-battle survival. Archetype: The Survivor.' ],

    // Volume 5
    ['name' => 'The "Soul Escape" (Hanyou)', 'description' => 'Character so shocked or tired they die temporarily. Eyes blank white circles, mouth a ghostly oval, a ghostly version floats out of mouth. Body Posture: Knees weak or collapsing. Context: Extreme exhaustion or a terrible pun. Archetype: The Victim.' ],
    ['name' => 'The "Shark Teeth" (Aggressive Shout)', 'description' => 'Comedic rage. Mouth wide open with jagged sawtooth teeth. Eyes are white triangles or inverted arcs. Body Posture: Leaning forward, fist shaking. Context: Being annoyed by a teammate. Archetype: The Hothead.' ],
    ['name' => 'The "Waterfall" Tears (Gag Crying)', 'description' => 'Over-the-top sadness. Eyes squeezed shut with massive streams of tears. Mouth wide and wobbly. Body Posture: Clinging to someone, prostrate, or running away. Context: Begging or fake crying. Archetype: The Mascot.' ],
    ['name' => 'The "Dot" Eyes (The Blank Stare)', 'description' => 'Total lack of thought. Eyes replaced by two black dots. Mouth tiny line or missing. Body Posture: Perfectly stiff. Context: Not understanding or silence after a bad joke. Archetype: The Idiot, The Innocent.' ],
    ['name' => 'The "Cat Mouth" (The :3 Face)', 'description' => 'Mischief or feigned innocence. Mouth as a "3" or "w". Eyes large and shiny. Body Posture: Hands as paws. Context: Pranking or asking for a treat. Archetype: The Trickster.' ],

    // Volume 6
    ['name' => 'The "Gaan" (The Gloom Lines)', 'description' => 'Classic anime visual for shock or depression. Vertical blue/purple lines descend from forehead. Body Posture: Shoulders dropped. Context: Realizing you forgot ammo or hearing bad news. Archetype: The Strategist who failed.' ],
    ['name' => 'The "Pinprick" Pupil (Terror)', 'description' => 'Fight-or-flight instinct. Iris and pupil contract to a tiny dot in a large white sclera. Body Posture: Back arched away; hands raised defensively. Context: Facing death or a jump scare. Archetype: Everyone facing a monster.' ],
    ['name' => 'The "Shadow Mask" (The Realization)', 'description' => 'Heavy realization darkens the mood. Hard straight shadow line across forehead and eyes. Mouth tight and trembling. Body Posture: Looking down at hands, knuckles white. Context: Realizing a betrayal. Archetype: The Traitor.' ],
    ['name' => 'The "Color Drain" (Pallor)', 'description' => 'Blood leaving the face. Skin tone shifts to pale grey/blue. Lips lose color. Dark circles appear. Body Posture: Nausea reaction. Context: Poisoning or extreme sickness. Archetype: The Rookie.' ],
    ['name' => 'The "Split" (Disbelief)', 'description' => 'Brain rejecting what the eyes see. One eyebrow shot up, the other furrowed. Mouth slightly agape crooked. Body Posture: Half-step back, hands open in "what?" gesture. Context: Impossible magic or a plot twist. Archetype: The Detective.' ],

    // Volume 7
    ['name' => 'The "Lumen" Pulse (Energy Emotion)', 'description' => 'When a being of light feels intense emotion. Visual: Core brightness flares. Anger = hard and jagged glow; Sadness = dim ember; Joy = pulsing sparkle particles. Context: Spirit Companion reacting to mood. Archetype: The Spirit Companion.' ],
    ['name' => 'The "Aperture" Squint (Mechanical Focus)', 'description' => 'Robotic narrowing of the eyes. Visual: Mechanical iris rotates/contracts to pinhole. Audio: "Whir-Click" hud overlay. Context: Gearbit scanning a threat. Archetype: The AI, The Cyborg.' ],
    ['name' => 'The "Glitch" Shudder (Data Stress)', 'description' => 'Digital pain/confusion. Visual: Hologram tears apart, chromatic aberration, databending. Body Posture: Projection flickers. Context: Being hacked or logic error. Archetype: The Hologram.' ],
    ['name' => 'The "Jagged" Silhouette (Shadow Aggression)', 'description' => 'A fluid enemy becomes hostile: smoky outline becomes spiked. Eyes elongate to slits. Body Posture: Expanding to loom; tentacles lash out. Context: Shadow sensing a threat. Archetype: The Shadow Manifestation.' ],
    ['name' => 'The "Icon" Face (Digital Shorthand)', 'description' => 'Retro-tech or cute AI communicating via neon ASCII emote: Shock: ( ! ), Love: <3, Confusion: ?, Death: X_X. Context: Mascot bot signaling emotions. Archetype: The Mascot Bot.' ],
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
            $filename = "generatives_expressions_{$frameId}_" . date('Ymd_His') . ".sql";
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
<title>Generate Expressions SQL</title>
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
    <h1>Generate `generatives` INSERTs (Expressions)</h1>
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
