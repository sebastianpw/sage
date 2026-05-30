// public/vedtriccs/js/vt-render.js
// Render module stub — polling helpers and render state management
// Most render logic lives in vt-transitions.js (vtRenderConnector, vtPollRenderJob)
// This module handles multi-connector bulk renders and status aggregation.

'use strict';

// ─── Bulk Render All Connectors ───────────────────────────────────────────────
// Renders every connector on the timeline that hasn't been rendered yet.
// Called from a future "Render All" menu button.
function vtRenderAllConnectors() {
    const sorted = vtGetSortedClipsPerTrack();
    let count = 0;
    for (const [, clips] of Object.entries(sorted)) {
        for (let i = 0; i < clips.length - 1; i++) {
            const a = clips[i], b = clips[i + 1];
            const key  = vtConnectorKey(a, b);
            const conn = VT_STATE.connectors[key];
            // Only render if not already done or in progress
            if (!conn || (!conn.videoId && conn.jobStatus !== 'processing' && conn.jobStatus !== 'queued')) {
                count++;
                // Stagger requests to avoid hammering PyAPI
                setTimeout(() => {
                    VT_STATE.selectedConnKey = key;
                    vtSelectConnector(key);
                    vtRenderConnector();
                }, count * 400);
            }
        }
    }
    if (count === 0) {
        Toast.show('All connectors already rendered', 'info');
    } else {
        Toast.show(`Queuing ${count} render${count > 1 ? 's' : ''}…`, 'info');
    }
}

// ─── Connector Status Summary ──────────────────────────────────────────────────
// Returns a summary object: { total, rendered, pending, failed }
function vtConnectorStatusSummary() {
    const sorted = vtGetSortedClipsPerTrack();
    let total = 0, rendered = 0, pending = 0, failed = 0;
    for (const [, clips] of Object.entries(sorted)) {
        for (let i = 0; i < clips.length - 1; i++) {
            total++;
            const key  = vtConnectorKey(clips[i], clips[i+1]);
            const conn = VT_STATE.connectors[key];
            if (!conn) continue;
            if (conn.videoId)                                                           rendered++;
            else if (conn.jobStatus === 'processing' || conn.jobStatus === 'queued')    pending++;
            else if (conn.jobStatus === 'failed')                                        failed++;
        }
    }
    return { total, rendered, pending, failed };
}

// ─── Resume polling for any in-progress jobs ─────────────────────────────────
// Called on page load to pick up renders that survived a page refresh.
// (Requires job IDs stored in connector metadata — available after vtDeserialize.)
function vtResumeRenderPolling() {
    for (const [key, conn] of Object.entries(VT_STATE.connectors)) {
        if (conn.jobId && (conn.jobStatus === 'processing' || conn.jobStatus === 'queued')) {
            vtPollRenderJob(key, conn.jobId);
        }
    }
}
