<?php
// public/view_gen_expressions3_sql.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$message = '';
$error = '';
$sql = '';

$expressions = [
    // Volume 13: The Fourth Wall Break
    ['name' => 'The "Camera Stare" (Audience Acknowledgment)', 'description' => 'Breaking the illusion. Facial Visual: Eyes shift to look directly at viewer/camera while everyone else looks elsewhere. Expression can range from exasperated to conspiratorial. One eyebrow may raise knowingly. Body Posture: Body remains in scene naturally, but head turns slightly toward camera. Sometimes includes small gesture (shrug, eye roll, thumbs up) meant only for audience. Context: Reacting to another character\'s stupidity, acknowledging absurdity of situation, or sharing private joke with viewer. Archetype: The Self-Aware Protagonist, The Narrator, The Comic Relief.'],
    ['name' => 'The "Thought Bubble Reaction" (Visible Internal)', 'description' => 'When inner thoughts become embarrassingly visible. Facial Visual: Eyes wide in horror, looking up at their own thought bubble. Face goes pale or red. Mouth in shocked O shape or clenched tight. Visual Effects: Thought bubble appears above head with embarrassing thoughts/images. Character visibly tries to swat it away or hide it. Body Posture: Reaching up frantically to grab/dispel the bubble. Backing away from it. Checking if others can see it. Context: Daydreaming about crush when they appear, thinking mean thoughts that manifest, or internal panic becoming external. Archetype: The Awkward Teen, The Telepath losing control, The Comedic Lead.'],
    ['name' => 'The "Narrator Argument" (Text Box Fight)', 'description' => 'Fighting with the story itself. Facial Visual: Looking up/around at narration boxes with annoyance or confusion. Mouth open arguing with disembodied voice. Eyebrows furrowed in frustration. Visual Effects: Narration text boxes appear. Character points at them, tries to erase them, or covers them with hands. Body Posture: Gesticulating wildly at the text. Hands on hips in defiant stance. Sometimes jumping to try to reach floating text. Context: Narrator exposing their secrets, correcting their lies, or providing unwanted commentary. Character defending themselves to the reader. Archetype: The Unreliable Narrator, The Fourth-Wall Breaker, The Meta Character.'],
    ['name' => 'The "Panel Break" (Frame Violation)', 'description' => 'Escaping the boundaries of reality. Visual Effects: Character\'s body parts extend beyond or break through the panel borders. Cracks appear in the frame. Sometimes they lean on the panel edge. Facial Visual: Smug awareness or desperate escape attempt. Eyes acknowledging the transgression. Body Posture: Climbing out of the panel, reaching into adjacent panels, or pulling the frame wider. Context: Extreme reaction that cannot be contained, comedic escape attempt, or showing god-tier power. Archetype: The Reality Warper, The Trickster God, The Cartoon Character.'],
    ['name' => 'The "Page Turn Anticipation" (Waiting for Reader)', 'description' => 'Frozen in anticipation of the reveal. Facial Visual: Expression locked in suspense - mouth open mid-gasp, eyes wide, or dramatic pointing. Completely still. Visual Effects: Speed lines frozen in place. Dramatic background locked. Sometimes TO BE CONTINUED text appears. Body Posture: Dynamic action pose held unnaturally still. Hand extended. Mid-leap or mid-fall. Context: Cliffhanger moment, dramatic reveal about to happen, or character literally waiting for reader to turn page. Archetype: The Cliffhanger Victim, The Dramatic Revealer, The Patient Character.'],

    // Volume 14: Time & Memory
    ['name' => 'The "Flashback Fade" (Memory Drift)', 'description' => 'The mind wandering to the past. Facial Visual: Eyes become unfocused and distant, pupils dilated. Gaze directed at nothing. Mouth slightly parted. Expression softens or saddens. Visual Effects: Soft white or sepia overlay beginning at edges. Background blurs or fades. Sometimes sparkles or film grain appears. Body Posture: Body goes still. Hand might reach out to phantom memory. Head tilts slightly. Context: Triggered by familiar smell/sound/sight. Reminiscing. Trauma response. Nostalgic moment. Archetype: The Veteran, The Orphan, The Reminiscer, The PTSD Sufferer.'],
    ['name' => 'The "Déjà Vu Overlap" (Time Echo)', 'description' => 'Experiencing the same moment twice. Visual Effects: Two semi-transparent versions of the same expression overlap slightly offset. Colors may split (chromatic aberration). Echo/ghost trails. Facial Visual: Confusion and disorientation. Eyes trying to focus. Brow furrowed. Slight head shake. Body Posture: Stumbling or touching head. Hand extended as if seeing double. Context: Time loop realization, prophetic vision manifesting, or supernatural time manipulation. Archetype: The Time Traveler, The Seer, The Loop Prisoner.'],
    ['name' => 'The "Photo Freeze" (Captured Moment)', 'description' => 'Reality pausing like a photograph. Visual Effects: Character and immediate surroundings freeze in perfect stillness. Color may shift to black & white or sepia. Film grain or photo border appears. Facial Visual: Expression captured mid-emotion - genuine smile, laughter, surprise. Natural and unposed. Body Posture: Casual, natural pose. Often mid-movement. Surrounded by friends/loved ones also frozen. Context: Precious memory being preserved, end of arc/episode, or magical time stop. Archetype: The Memory Keeper, The Photographer, The Time Stopper.'],
    ['name' => 'The "Fast Forward Blur" (Time Acceleration)', 'description' => 'Watching time pass rapidly. Visual Effects: Character\'s expression cycles rapidly through multiple emotions as background blurs/streaks. Motion lines everywhere. Facial Visual: Features slightly distorted by speed. Expression changing so fast it blurs together. Body Posture: Standing still while world moves around them, or moving so fast they\'re blurred. Context: Training montage from their POV, waiting impatiently, or time manipulation power. Archetype: The Impatient, The Time Manipulator, The Training Protagonist.'],
    ['name' => 'The "Slow Motion Realization" (Extended Moment)', 'description' => 'A split second stretched into eternity. Visual Effects: Everything moves in extreme slow motion. Individual sweat drops frozen mid-air. Dust particles visible. Sound effects drawn out. Facial Visual: Expression slowly morphing from calm to horror/realization. Eyes widening frame by frame. Body Posture: Reaching out desperately but moving too slowly. Every muscle visible in strain. Context: Seeing the bullet/attack coming, realizing terrible truth, or heroic sacrifice moment. Archetype: The Doomed Hero, The Witness, The Awakening.'],

    // Volume 15: Social Hierarchy
    ['name' => 'The "Ojou-sama Laugh" (Noble Mockery)', 'description' => 'The classic drill-hair princess laugh. Facial Visual: Eyes closed in delight (or one open mockingly). Mouth open in elegant laugh. Expression radiating superiority. Body Posture: Hand covering mouth delicately (back of hand, fingers curled). Head tilted back. Other hand on hip or holding fan. Audio Visual: Ohohoho or Hohoho text. Musical notes floating. Context: Mocking social inferiors, responding to compliments, or masking insecurity with arrogance. Archetype: The Ojou-sama, The Rich Girl, The Rival Noble.'],
    ['name' => 'The "Dogeza Desperation" (Ultimate Apology)', 'description' => 'The full prostration of absolute submission. Facial Visual: Face pressed to ground (often not visible). If visible: eyes squeezed shut, mouth in grimace of desperation. Body Posture: Entire body flat on ground. Forehead touching floor. Arms extended forward or pressed at sides. Absolute prostration. Context: Begging for forgiveness, extreme apology, absolute submission, or desperate plea. Archetype: The Guilty, The Desperate, The Submissive, The Debtor.'],
    ['name' => 'The "Senpai Notice" (Hopeful Recognition)', 'description' => 'The yearning for acknowledgment from upperclassman. Facial Visual: Eyes wide and hopeful, looking upward. Small hopeful smile. Light blush. Eyebrows raised in anticipation. Body Posture: Hands clasped at chest (prayer pose). Leaning forward slightly. Body language open and eager. Visual Effects: Sparkles around head. Flowers in background. Hopeful aura. Context: Waiting for senpai to notice them, hoping for acknowledgment, or seeking approval from superior. Archetype: The Kouhai, The Admirer, The Student, The Junior.'],
    ['name' => 'The "Kouhai Puff" (Trying to Impress)', 'description' => 'Attempting to appear mature/capable and failing adorably. Facial Visual: Chest puffed out with pride. Attempting serious/mature expression but looking cute. Small determined frown. Body Posture: Standing extra straight. Chest out. Chin up. Hands on hips or crossed. Trying to look bigger. Visual Effects: Confidence aura attempting to manifest but flickering. Small sweat drops showing the effort. Context: Trying to impress senpai, prove capability, or act mature. Usually undermined by cute appearance or mistake. Archetype: The Eager Kouhai, The Little Sister, The Rookie.'],
    ['name' => 'The "Servant Bow" (Professional Deference)', 'description' => 'The perfect butler/maid acknowledgment. Facial Visual: Completely neutral professional expression. Eyes closed or lowered respectfully. Small polite smile. Body Posture: Precise formal bow - hand over heart or crossed at waist. Back straight. Movements elegant and measured. Context: Acknowledging master\'s orders, professional service, or concealing true emotions behind perfect etiquette. Archetype: The Butler, The Maid, The Loyal Servant, The Professional.'],

    // Volume 16: Food Reaction
    ['name' => 'The "Sparkle Taste" (Transcendent Flavor)', 'description' => 'When food is so good it becomes a religious experience. Facial Visual: Eyes enormous and sparkling with literal stars, flowers, or light beams. Mouth open in bliss. Cheeks glowing/rosy. Visual Effects: Sparkles radiating from face. Background explodes into flowers, stars, or heavenly light. Sometimes wings appear. Body Posture: Body arched back in ecstasy or leaning forward in reverence. Hands clasped. Sometimes floating. Context: Tasting incredible food, homemade cooking from love interest, or master chef\'s creation. Archetype: The Food Critic, The Gourmand, The Appreciator.'],
    ['name' => 'The "Soul Departure (Food)" (Delicious Death)', 'description' => 'Food so good the spirit leaves the body. Facial Visual: Eyes rolled back or closed in bliss. Mouth hanging open. Expression of pure ecstasy. Visual Effects: Translucent ghost/soul floating upward from body. Body remains seated/standing but lifeless. Body Posture: Body slumped in chair or swaying. Arms hanging. Completely limp. Context: First bite of amazing food, nostalgic flavor, or competition-winning dish. Archetype: The Food Lover, The Judge, The Overwhelmed.'],
    ['name' => 'The "Cheek Stuff" (Greedy Hamster)', 'description' => 'Hoarding food in cheeks adorably. Facial Visual: Cheeks bulging outward comically (chipmunk/hamster effect). Eyes squeezed into happy crescents. Mouth completely full. Body Posture: Hunched protectively over food. Arms curled around plate. Guarding the feast. Context: Eating enthusiastically, protecting food from others, or storing food like an animal. Archetype: The Big Eater, The Poor Student, The Food Guardian.'],
    ['name' => 'The "Spice Death" (Too Hot!)', 'description' => 'The agony of excessive heat/spice. Facial Visual: Eyes bulging, tears streaming. Tongue hanging out. Face bright red. Steam coming from head/mouth. Visual Effects: Literal flames in background. Heat waves visible. Smoke/steam from mouth and ears. Body Posture: Fanning mouth desperately. Grabbing for water. Running in circles. Context: Eating too-spicy food, being tricked with hot sauce, or cultural food challenge. Archetype: The Victim, The Challenger, The Foreigner.'],
    ['name' => 'The "Hunger Collapse" (Starvation Mode)', 'description' => 'When hunger becomes a dramatic crisis. Facial Visual: Eyes become spirals or X\'s. Face pale and gaunt. Mouth open weakly. Visual Effects: Soul/ghost partially leaving body. Stomach growling visualized as sound effect text or monster. Body Posture: Collapsed on ground or table. Crawling weakly. Hand reaching toward food desperately. Context: Missing meals, being broke, or after intense training/battle. Archetype: The Poor Student, The Fighter, The Starving Artist.'],

    // Volume 17: Villain Signature
    ['name' => 'The "Hand Cover Smirk" (Concealed Evil)', 'description' => 'Hiding the evil grin. Facial Visual: Hand covering lower half of face (mouth and chin). Only eyes visible - often narrowed and gleaming with malice. Body Posture: Relaxed but predatory. Often sitting or leaning. Free hand gesturing casually. Context: Plotting, concealing true intentions, or savoring imminent victory. Archetype: The Manipulator, The Chessmaster Villain, The Traitor.'],
    ['name' => 'The "Throne Slouch" (Bored Dominance)', 'description' => 'The ruler too powerful to care. Facial Visual: Utterly bored expression. Eyes half-closed or looking away with disinterest. Small frown or neutral mouth. Body Posture: Slouched carelessly on throne. Head resting on hand. Legs crossed or draped. Body language screaming indifference. Context: Listening to subordinates, dismissing threats, or showing supreme confidence through apathy. Archetype: The Demon King, The Emperor, The Overpowered Villain.'],
    ['name' => 'The "Champagne Tilt" (Chaos Toast)', 'description' => 'Celebrating others\' misery. Facial Visual: Elegant smile or smirk. Eyes half-closed in satisfaction. Completely relaxed expression. Body Posture: Holding wine glass delicately. Tilting it toward chaos/destruction. Seated comfortably or standing at window. Context: Watching plan unfold, observing heroes struggle, or celebrating destruction from safety. Archetype: The Mastermind, The Rich Villain, The Spectator.'],
    ['name' => 'The "Monocle Gleam" (Single Lens Menace)', 'description' => 'The aristocratic threat. Facial Visual: One eye hidden behind bright white gleaming monocle. Visible eye narrowed. Small knowing smile. Body Posture: Adjusting monocle with one finger. Standing straight with perfect posture. Cane or hand in pocket. Context: Revealing information, making a threat, or showing intellectual superiority. Archetype: The Gentleman Villain, The Mad Scientist, The Aristocrat.'],
    ['name' => 'The "Finger Bridge" (Contemplative Evil)', 'description' => 'The classic Gendo pose. Facial Visual: Lower face obscured behind steepled fingers. Eyes visible above hands - cold and calculating. Small smirk hidden. Body Posture: Seated, leaning forward. Elbows on desk/armrests. Fingers pressed together in bridge/steeple shape. Context: Deep in thought, interrogating, or revealing master plan. Archetype: The Manipulator, The Director, The Evil Genius.'],

    // Volume 18: Battle Damage
    ['name' => 'The "Tattered Determination" (Heroic Persistence)', 'description' => 'Still standing despite everything. Facial Visual: Dirt and blood on face. One eye swollen or closed. Determined glare from remaining eye. Teeth gritted or grinning fiercely. Visual Effects: Clothing torn and tattered. Visible wounds. Smoke/steam rising from body. Body Posture: Swaying but standing. Weapon clutched tightly. Refusing to kneel. Context: Final stand, refusing to give up, or protecting someone despite injuries. Archetype: The Shonen Hero, The Guardian, The Last Stand.'],
    ['name' => 'The "One Eye Closed (Injury)" (Fighting Wounded)', 'description' => 'Continuing combat despite damage. Facial Visual: One eye forcibly closed from injury (swelling, blood, damage). Other eye blazing with determination. Grimace of pain masked by resolve. Body Posture: Slightly favoring one side. Compensating for reduced vision. Still in fighting stance. Context: Mid-battle injury, losing an eye permanently, or temporary blindness. Archetype: The Wounded Warrior, The Survivor, The Determined.'],
    ['name' => 'The "Blood Drip Grin" (Savage Joy)', 'description' => 'Enjoying the fight despite injury. Facial Visual: Wide, almost manic grin. Blood dripping from mouth or forehead. Eyes wild with battle joy. Body Posture: Loose, relaxed despite injuries. Wiping blood with thumb. Laughing. Context: Berserker enjoying combat, finding worthy opponent, or breaking through limits. Archetype: The Battle Maniac, The Berserker, The Blood Knight.'],
    ['name' => 'The "Cracked Armor" (Defense Breaking)', 'description' => 'The moment the protection fails. Visual Effects: Visible cracks spreading across armor, shield, or defensive magic. Light bleeding through cracks. Facial Visual: Eyes widening in realization. Sweat appearing. Confident expression cracking into fear. Body Posture: Looking down at damaged protection. Defensive stance weakening. Context: Powerful attack breaking through, defense reaching limit, or invincibility ending. Archetype: The Tank, The Shielded, The Defender.'],
    ['name' => 'The "Victory Collapse" (Last Strength)', 'description' => 'Standing just long enough to win. Facial Visual: Relieved smile or grin. Eyes closing. Expression peaceful despite injuries. Body Posture: Knees buckling. Beginning to fall. Weapon dropping from hand. Context: Defeating enemy with last strength, protecting someone until safe, or completing mission before collapse. Archetype: The Hero, The Protector, The Sacrifice.'],

    // Volume 19: Musical & Performance
    ['name' => 'The "Wink & Point" (Classic Idol)', 'description' => 'The signature idol gesture. Facial Visual: One eye closed in wink. Other eye bright and sparkling. Big smile showing teeth. Body Posture: Pointing directly at viewer/camera. Hip cocked to side. Body in dynamic pose. Visual Effects: Sparkles, stars, or hearts around the gesture. Light rays. Context: Stage performance, photo shoot, or charming fans. Archetype: The Idol, The Performer, The Charmer.'],
    ['name' => 'The "Peace Sign Tilt" (Cute Pose)', 'description' => 'The adorable photo pose. Facial Visual: Bright smile. Eyes closed in happy crescents or one winking. Head tilted at angle. Body Posture: V-sign (peace sign) raised near face. Head resting on or near the hand. Context: Taking photos, greeting fans, or being deliberately cute. Archetype: The Idol, The Cute Character, The Influencer.'],
    ['name' => 'The "Microphone Passion" (Performance Soul)', 'description' => 'Pouring emotion into the song. Facial Visual: Eyes squeezed shut or staring intensely ahead. Mouth wide open singing. Expression of pure emotion (joy, pain, love). Body Posture: Gripping microphone with both hands or one hand while other reaches out. Body leaning into the performance. Visual Effects: Sound waves visible. Spotlights. Emotion radiating outward. Context: Emotional climax of song, reaching for high note, or connecting with audience. Archetype: The Singer, The Performer, The Artist.'],
    ['name' => 'The "Backstage Collapse" (Performance Exhausted)', 'description' => 'The energy drop after the show. Facial Visual: Exhausted relief. Sweat dripping. Small satisfied smile despite tiredness. Body Posture: Slumped against wall or in chair. Arms hanging limp. Still in costume but disheveled. Context: After successful performance, behind the scenes, or dropping the idol facade. Archetype: The Performer, The Hard Worker, The Real Person.'],
    ['name' => 'The "Dance Freeze" (Choreography Pose)', 'description' => 'The dramatic dance position. Facial Visual: Intense expression or joyful smile. Eyes focused. Perfectly composed despite dynamic pose. Body Posture: Mid-dance pose - leg extended, arms positioned artistically. Perfect balance. Visual Effects: Motion lines frozen. Ribbons or clothing mid-flutter. Context: Peak moment in choreography, promotional poster pose, or musical number climax. Archetype: The Dancer, The Idol, The Performer.'],

    // Volume 20: Supernatural Status
    ['name' => 'The "Possessed Puppet" (Loss of Control)', 'description' => 'Being controlled by external force. Visual Effects: Glowing strings, dark tendrils, or mystical chains attached to limbs/body. Body moving unnaturally. Facial Visual: Eyes blank, glowing differently, or fighting for control (one eye normal, one possessed). Mouth slack or forced into smile. Body Posture: Jerky, puppet-like movements. Unnatural angles. Fighting against invisible strings. Context: Mind control, possession, curse activation, or being a literal puppet. Archetype: The Possessed, The Puppet, The Controlled.'],
    ['name' => 'The "Split Personality Switch" (Instant Change)', 'description' => 'Complete personality flip mid-scene. Facial Visual: Instant transformation - one frame shy/innocent, next frame confident/evil. Different eye color. Complete expression change. Visual Effects: Flash of light or darkness. Hair may shift. Aura changes completely. Body Posture: Entire posture changes instantly. Slouched to straight, or confident to cowering. Context: Alternate personality emerging, possession taking over, or true nature revealing. Archetype: The Split Personality, The Jekyll/Hyde, The Shapeshifter.'],
    ['name' => 'The "Curse Mark Pulse" (Affliction Active)', 'description' => 'The curse responding to emotion. Visual Effects: Magical tattoo/mark glowing brighter. Pulsing with heartbeat. Spreading across skin. Facial Visual: Gritting teeth against pain or power. Eyes glowing same color as mark. Sweat from strain. Body Posture: Hand clutching marked area. Body trembling. Fighting against the power. Context: Curse activating, forbidden power being used, or affliction spreading. Archetype: The Cursed, The Marked One, The Afflicted.'],
    ['name' => 'The "Halo Appearance" (Divine Manifestation)', 'description' => 'Showing celestial nature. Visual Effects: Glowing ring/halo appearing above or behind head. Wings of light. Holy aura. Facial Visual: Serene expression. Eyes glowing gently or pure white. Peaceful smile. Body Posture: Arms slightly spread. Floating. Relaxed but radiating power. Context: Angel revealing identity, divine power activation, or achieving enlightenment. Archetype: The Angel, The Saint, The Chosen One.'],
    ['name' => 'The "Corruption Spread" (Darkness Claiming)', 'description' => 'Evil/curse visibly taking over. Visual Effects: Black veins/cracks spreading across skin. Darkness oozing from eyes/mouth. Color draining from one side. Facial Visual: Half face normal, half corrupted. Expression of horror or acceptance. Fighting against change. Body Posture: One side of body moving normally, other side jerky/corrupted. Transformation in progress. Context: Succumbing to darkness, virus spreading, or corruption claiming victim. Archetype: The Corrupted, The Infected, The Fallen.'],
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
            $filename = "generatives_expressions3_{$frameId}_" . date('Ymd_His') . ".sql";
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
<title>Generate Expressions 3 SQL</title>
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
    <h1>Generate `generatives` INSERTs (Expressions 3 - Volumes 13-20)</h1>
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