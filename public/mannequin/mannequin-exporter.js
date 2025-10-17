class MannequinExporter {
    constructor() {
        this.editor = null;
        this.model = null;
        this.panel = null;

        this.waitForEditor();
        this.waitForButtonGroups();
    }

    // -------------------------------
    // Wait for the 3D editor
    // -------------------------------
    waitForEditor() {
        if (window.editor && window.editor.figure) {
            this.editor = window.editor;
            this.model = this.editor.figure;
            console.log("[MannequinExporter] Editor found!");
        } else {
            setTimeout(() => this.waitForEditor(), 500);
        }
    }

    // -------------------------------
    // Wait for the DOM button groups
    // -------------------------------
    waitForButtonGroups() {
        const groups = document.querySelectorAll('span.button-group');
        if (groups.length > 0) {
            this.addButtonGroups(groups);
        } else {
            setTimeout(() => this.waitForButtonGroups(), 500);
        }
    }

    // -------------------------------
    // Add new button group next to existing ones
    // -------------------------------
    addButtonGroups(groups) {
        groups.forEach(group => {
            const newGroup = document.createElement('span');
            newGroup.className = 'button-group';
            newGroup.style.marginLeft = '5px';

            const btnJSON = document.createElement('button');
            btnJSON.textContent = "Export JSON";
            btnJSON.onclick = () => this.exportJSON();
            newGroup.appendChild(btnJSON);

            const btnPNG = document.createElement('button');
            btnPNG.textContent = "Export PNG";
            btnPNG.onclick = () => this.exportPNG();
            newGroup.appendChild(btnPNG);

            group.after(newGroup);
        });
        console.log("[MannequinExporter] Added new button groups next to existing ones.");
    }

    // -------------------------------
    // Export JSON from postureString
    // -------------------------------
    exportJSON() {
        if (!this.model || !this.model.postureString) return;

        const blob = new Blob([this.model.postureString], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'mannequin_posture.json';
        a.click();
    }

    // -------------------------------
    // Export PNG skeleton from postureString
    // -------------------------------
    exportPNG(width = 640, height = 480) {
        if (!this.model || !this.model.postureString) return;

        let data;
        try {
            data = JSON.parse(this.model.postureString);
        } catch (e) {
            console.error("Invalid postureString JSON");
            return;
        }

        const bodyKeypoints = data.body || [];
        const lHandKeypoints = data.l_hand || [];
        const rHandKeypoints = data.r_hand || [];

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');

        const toCanvas = (kp) => [kp[0] * width, kp[1] * height];

        ctx.fillStyle = 'red';
        [bodyKeypoints, lHandKeypoints, rHandKeypoints].forEach(kps => {
            kps.forEach(kp => {
                const [x, y] = toCanvas(kp);
                ctx.beginPath();
                ctx.arc(x, y, 5, 0, 2 * Math.PI);
                ctx.fill();
            });
        });

        // Simple skeleton connections (COCO 18 body parts subset)
        const skeleton = [
            [0,1],[1,2],[2,3],[3,4],[1,5],[5,6],[6,7],[1,8],[8,9],[9,10],[10,11],
            [8,12],[12,13],[13,14]
        ];
        ctx.strokeStyle = 'blue';
        ctx.lineWidth = 2;
        skeleton.forEach(([a,b]) => {
            if (bodyKeypoints[a] && bodyKeypoints[b]) {
                const [x1,y1] = toCanvas(bodyKeypoints[a]);
                const [x2,y2] = toCanvas(bodyKeypoints[b]);
                ctx.beginPath();
                ctx.moveTo(x1,y1);
                ctx.lineTo(x2,y2);
                ctx.stroke();
            }
        });

        const a = document.createElement('a');
        a.href = canvas.toDataURL('image/png');
        a.download = 'mannequin_openpose.png';
        a.click();
    }
}

// -------------------------------
// Initialize exporter
// -------------------------------
window.mannequinExporter = new MannequinExporter();
