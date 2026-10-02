<x-app-layout title="Report damage" :breadcrumbs="['Report damage']">
    <x-slot:head>@vite('resources/js/reports/create.js')</x-slot:head>
    <script type="application/json" id="reportConfig">@json($config)</script>

    <div class="row justify-content-center">
        <div class="col-xl-7 col-lg-9">
            <x-page-header title="Report damage" subtitle="Reports must be made at the location. GPS identifies the road automatically." />

            <form id="reportForm" novalidate>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><span class="badge text-bg-primary me-1">1</span> Your location</span>
                        <span id="gpsAccuracy" class="badge text-bg-secondary">GPS…</span>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <span id="gpsCoords">Waiting for GPS…</span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-2" id="refreshGps">Re-check</button></div>
                        <div id="locationStatus"><span class="text-muted">Allow location access when your browser asks.</span></div>
                        @if ($config['canOverride'])
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="overrideToggle">
                                <label class="form-check-label small" for="overrideToggle"><strong>Test mode:</strong> pick the location on a map instead of GPS. The report is marked TEST.</label>
                            </div>
                            <div id="overrideMapWrap" class="d-none mt-2"><div id="overrideMap" style="height: 320px" class="rounded border" data-config='@json($config['map'])'></div></div>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><span class="badge text-bg-primary me-1">2</span> What is damaged?</div>
                    <div class="card-body">
                        <div id="typeChoices" class="d-flex flex-wrap gap-2 mb-3"></div>
                        <div class="small fw-semibold mb-1">Type of damage</div>
                        <div id="categoryChoices" class="d-flex flex-wrap gap-2 mb-3"></div>
                        <div class="small fw-semibold mb-1">Severity</div>
                        <div id="severityChoices" class="d-flex flex-wrap gap-2"></div>
                        <div id="duplicateWarning" class="mt-3"></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><span class="badge text-bg-primary me-1">3</span> Photos &amp; video</div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <label class="btn btn-primary btn-lg mb-0"><i class="bi bi-camera"></i> Take photo
                                <input type="file" id="photoInput" accept="image/*" capture="environment" multiple hidden></label>
                            @if ($config['evidence']['maxVideos'] > 0)
                                <label class="btn btn-outline-primary btn-lg mb-0"><i class="bi bi-camera-video"></i> Record video
                                    <input type="file" id="videoInput" accept="video/*" capture="environment" hidden></label>
                            @endif
                        </div>
                        <div class="small text-muted mb-2" id="evidenceHelp"></div>
                        <div class="row g-2" id="evidenceList"></div>
                        <div class="form-text">Location and time are recorded with every photo. A watermarked copy is shown to officers; the original is kept unchanged.</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><span class="badge text-bg-primary me-1">4</span> Short description <span class="text-muted small fw-normal">(optional)</span></div>
                    <div class="card-body">
                        <textarea id="description" class="form-control" rows="2" maxlength="1000" placeholder="e.g. Deep pothole on the left lane near the culvert"></textarea>
                    </div>
                </div>

                <div id="submitErrors"></div>
                <button id="submitBtn" class="btn btn-success btn-lg w-100 mb-4" disabled><i class="bi bi-send"></i> Submit report</button>
            </form>
        </div>
    </div>
</x-app-layout>
