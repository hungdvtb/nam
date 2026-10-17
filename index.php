<?php
?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trình huấn luyện giao dịch AI</title>
    <link rel="stylesheet" href="assets/styles.css?v=20261015-case-drag-overlay">
    <link rel="stylesheet" href="assets/pa-workspace.css?v=20261015-clear-skip-case-button">
</head>
<body>
    <div class="app-shell">
        <div class="navigation-drawer" id="navigationDrawer">
        <button class="navigation-reveal" id="navigationReveal" type="button" aria-label="Mở menu điều hướng" title="Mở menu điều hướng" aria-expanded="false" aria-controls="appSidebar"><span aria-hidden="true">›</span></button>
        <aside class="sidebar" id="appSidebar" aria-label="Menu chính" inert>
            <div class="brand">
                <span class="brand-mark">AT</span>
                <div>
                    <strong>Huấn luyện giao dịch AI</strong>
                    <span>Forex H4 / M15</span>
                </div>
            </div>
            <nav class="nav-tabs" aria-label="Điều hướng chính">
                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-goal">
                        <span class="nav-section-title">1. Mục tiêu</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-goal" hidden>
                        <button class="nav-tab is-active" data-tab="dashboard">Tổng quan</button>
                        <button class="nav-tab" data-tab="accounts">Tài khoản</button>
                        <button class="nav-tab" data-tab="kpi-profiles">Cấu hình KPI</button>
                    </div>
                </div>

                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-data">
                        <span class="nav-section-title">2. Dữ liệu</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-data" hidden>
                        <button class="nav-tab" data-tab="mt5">Dữ liệu & MT5</button>
                    </div>
                </div>

                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-strategy">
                        <span class="nav-section-title">3. Chiến lược</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-strategy" hidden>
                        <button class="nav-tab" data-tab="strategy">Luật chiến lược</button>
                        <button class="nav-tab" data-tab="strategy-versions" hidden aria-hidden="true" tabindex="-1">Phiên bản</button>
                        <button class="nav-tab" data-tab="playbook" hidden aria-hidden="true" tabindex="-1">Sổ chiến lược</button>
                    </div>
                </div>

                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-backtest">
                        <span class="nav-section-title">4. Backtest</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-backtest" hidden>
                        <button class="nav-tab" data-tab="backtest">Chạy backtest</button>
                        <button class="nav-tab" data-tab="backtest-v2">Kết quả & KPI</button>
                        <button class="nav-tab" data-tab="compare">So sánh</button>
                    </div>
                </div>
                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-research-lab">
                        <span class="nav-section-title">5. AI Lab</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-research-lab" hidden>
                        <button class="nav-tab" data-tab="research-lab">2 AI tự nghiên cứu</button>
                    </div>
                </div>

                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-ai">
                        <span class="nav-section-title">6. Tối ưu nâng cao</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-ai" hidden>
                        <button class="nav-tab" data-tab="ai-orchestrator" hidden aria-hidden="true" tabindex="-1">Công cụ kỹ thuật AI</button>
                        <button class="nav-tab" data-tab="openai-settings">Kết nối OpenAI</button>
                        <button class="nav-tab" data-tab="evolution">TradeBrain nâng cấp</button>
                    </div>
                </div>

                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-learning">
                        <span class="nav-section-title">7. Dạy & bộ nhớ AI</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-learning" hidden>
                        <button class="nav-tab" data-tab="trainer">Dạy AI</button>
                        <button class="nav-tab" data-tab="pa-training">Dạy Price Action</button>
                        <button class="nav-tab" data-tab="calibration">Hiệu chỉnh mẫu</button>
                        <button class="nav-tab" data-tab="tradebrain-memory">Bộ nhớ AI</button>
                    </div>
                </div>

                <div class="nav-section">
                    <button class="nav-section-toggle" type="button" aria-expanded="false" aria-controls="nav-section-advanced">
                        <span class="nav-section-title">8. Nâng cao</span>
                        <span class="nav-section-arrow" aria-hidden="true"></span>
                    </button>
                    <div class="nav-section-body" id="nav-section-advanced" hidden>
                        <button class="nav-tab" data-tab="experiments">Thí nghiệm AI</button>
                        <button class="nav-tab" data-tab="prompt-ai">Prompt AI</button>
                        <button class="nav-tab" data-tab="memory">Nhập/xuất bộ nhớ</button>
                    </div>
                </div>
            </nav>
            <div class="sidebar-footer">
                <span id="appStatus">Đang tải...</span>
            </div>
        </aside>

        </div>
        <main class="main">
            <section class="topbar">
                <div>
                    <h1 id="pageTitle">Tổng quan</h1>
                    <p id="pageSubtitle">Trạng thái rủi ro, bộ dữ liệu và các mẫu đã duyệt.</p>
                </div>
                <div class="topbar-actions">
                    <button class="button secondary" id="helpGuideBtn" type="button">Hướng dẫn</button>
                    <a class="button secondary" href="api.php?action=export_memory">Xuất bộ nhớ</a>
                    <button class="button" id="refreshBtn" type="button">Tải lại</button>
                </div>
            </section>

            <section class="tab-panel is-active" id="tab-dashboard">
                <div class="metric-grid" id="metricGrid"></div>

                <section class="panel funding-goal-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Kế hoạch pass quỹ trong 1 tháng</h2>
                            <span>AI sẽ dùng mục tiêu này để tự tìm chiến lược và đề xuất cách đi vốn/risk phù hợp, không đặt lệnh live.</span>
                        </div>
                        <span class="status-badge">Mục tiêu chính</span>
                    </div>
                    <div id="fundingGoalPlan"></div>
                </section>

                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Xem trước rủi ro</h2>
                            <span id="riskAccountName"></span>
                        </div>
                        <div class="form-grid compact" id="riskPreviewForm"></div>
                        <div class="risk-output" id="riskOutput"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Rà soát gần đây</h2>
                            <span id="recentCount">0</span>
                        </div>
                        <div class="review-list" id="recentReviews"></div>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-accounts">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Hồ sơ tài khoản</h2>
                            <button class="button secondary" id="newAccountBtn" type="button">Tạo mới</button>
                        </div>
                        <div class="profile-list" id="accountList"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Hồ sơ đang chọn</h2>
                            <button class="button" id="saveAccountsBtn" type="button">Lưu hồ sơ</button>
                        </div>
                        <form id="accountForm" class="form-grid"></form>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-strategy">
                <section class="panel strategy-builder-switch">
                    <div>
                        <h2>Cách tạo chiến lược</h2>
                        <p>Chọn tự nhập luật nếu bạn đã có phương pháp, hoặc để AI nội bộ tự sinh nhiều bộ luật rồi backtest.</p>
                    </div>
                    <div class="segmented-control" role="tablist" aria-label="Cách tạo chiến lược">
                        <button class="segment-button is-active" id="strategyManualModeBtn" type="button" data-strategy-builder-mode="manual">Tự nhập thủ công</button>
                        <button class="segment-button" id="strategyAiModeBtn" type="button" data-strategy-builder-mode="ai">AI nội bộ tự sinh</button>
                    </div>
                </section>

                <div class="strategy-builder-mode is-active" id="strategyManualBuilder" data-strategy-builder-panel="manual">
                    <div class="workspace-grid two">
                        <section class="panel">
                            <div class="panel-header">
                                <h2>Luật chiến lược thủ công</h2>
                                <button class="button" id="saveStrategyBtn" type="button">Lưu chiến lược</button>
                            </div>
                            <form id="strategyForm" class="form-grid"></form>
                        </section>

                        <section class="panel">
                            <div class="panel-header">
                                <h2>Mẫu vào lệnh</h2>
                                <span id="setupCount"></span>
                            </div>
                            <div class="setup-list" id="setupList"></div>
                            <div class="panel-subsection">
                                <div class="panel-header compact">
                                    <h2>Quản lý lệnh</h2>
                                </div>
                                <div class="management-list" id="managementList"></div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="strategy-builder-mode" id="strategyAiBuilder" data-strategy-builder-panel="ai" hidden>
                    <section class="panel strategy-version-panel auto-research-panel">
                        <div class="panel-header">
                            <div>
                                <h2>AI nội bộ tự sinh chiến lược</h2>
                                <span>Chạy bằng bộ sinh luật + backtest + chấm KPI trong app. Chưa cần OpenAI/Ollama, và không đặt lệnh MT5.</span>
                            </div>
                            <div class="topbar-actions">
                                <button class="button" id="runAutoResearchBtn" type="button">Bắt đầu tự tìm chiến lược</button>
                                <button class="button danger" id="stopAutoResearchBtn" type="button" hidden>Dừng sau lượt này</button>
                                <button class="button secondary" id="refreshAutoResearchBtn" type="button">Tải lại</button>
                            </div>
                        </div>
                        <div class="auto-research-workflow" id="autoResearchWorkflow">
                            <div class="workflow-step is-active">
                                <span>1</span>
                                <strong>Chọn phạm vi</strong>
                                <small>Phương pháp, nguồn AI, cặp tiền và KPI.</small>
                            </div>
                            <div class="workflow-step">
                                <span>2</span>
                                <strong>Chạy backtest</strong>
                                <small>App tự sinh rule, tự backtest, không đặt lệnh MT5.</small>
                            </div>
                            <div class="workflow-step">
                                <span>3</span>
                                <strong>Đọc kết quả</strong>
                                <small>Xem bản tốt nhất, lý do chưa đạt và bước tiếp theo.</small>
                            </div>
                            <div class="workflow-step">
                                <span>4</span>
                                <strong>Soi từng lệnh</strong>
                                <small>Mở M15/H4 trên chart và xem bảng lệnh.</small>
                            </div>
                        </div>
                        <form class="form-grid compact auto-research-form" id="autoResearchForm">
                            <label class="field full">
                                <span>Chế độ chạy</span>
                                <select id="autoResearchModeSelect">
                                    <option value="from_zero" selected>Tự sinh chiến lược từ số 0</option>
                                    <option value="optimize_existing">Tối ưu từ chiến lược có sẵn</option>
                                </select>
                                <small>Từ số 0 nghĩa là AI nội bộ tự tạo nhiều bộ luật mẫu rồi backtest, không cần bạn nhập chiến lược trước.</small>
                            </label>
                            <label class="field full">
                                <span>Phương pháp mẹ</span>
                                <select id="autoResearchMethodSelect">
                                    <option value="h4_trend_zone_m15_pa" selected>H4 Trend + H4 Zone + M15 Price Action</option>
                                    <option value="pa_sd_h4_m15">Price Action + Vùng canh buy/sell H4/M15 (rộng)</option>
                                    <option value="liquidity_sweep_pa">Liquidity sweep / false break</option>
                                    <option value="trend_pullback">Pullback theo xu hướng</option>
                                    <option value="range_sd_reversal">Range trading tại vùng canh buy/sell</option>
                                    <option value="free_explore">Tự do khám phá nhiều họ chiến lược</option>
                                </select>
                                <small>Mặc định khóa theo quy trình: H4 đọc xu hướng/vùng cản, M15 xác nhận Price Action đảo chiều rồi mới vào lệnh.</small>
                            </label>
                            <div class="research-method-card full" id="autoResearchMethodGuide"></div>
                            <div class="method-tournament-card full">
                                <div class="method-tournament-head">
                                    <label>
                                        <input id="autoResearchTournamentToggle" type="checkbox" checked>
                                        <span>Chạy giải đấu nhiều phương pháp</span>
                                    </label>
                                    <span id="autoResearchMethodCount">0 phương pháp được chọn</span>
                                </div>
                                <div class="method-checklist" id="autoResearchMethodChecklist"></div>
                                <small>Gợi ý tìm nhanh: chọn nhiều phương pháp, giảm số vòng xuống 3-5 để quét trước; sau đó chạy tiếp từ bản tốt nhất.</small>
                            </div>
                            <label class="field full">
                                <span>Nguồn AI phân tích</span>
                                <select id="autoResearchAiProviderSelect">
                                    <option value="openai_chat" selected>OpenAI API - dùng key và ngân sách ngày</option>
                                    <option value="internal">AI nội bộ - không tốn API</option>
                                </select>
                                <small id="autoResearchAiProviderNote">Mặc định dùng OpenAI API nếu key sẵn sàng và còn ngân sách ngày.</small>
                            </label>
                            <label class="field full">
                                <span>Cặp tiền</span>
                                <select id="autoResearchSymbolSelect" multiple hidden aria-hidden="true"></select>
                                <div class="symbol-picker" id="autoResearchSymbolPicker">
                                    <div class="symbol-picker-actions">
                                        <button class="button secondary" id="autoResearchSelectAllSymbolsBtn" type="button">Chọn tất cả</button>
                                        <button class="button secondary" id="autoResearchClearSymbolsBtn" type="button">Bỏ chọn</button>
                                        <span id="autoResearchSymbolCount">0 cặp được chọn</span>
                                    </div>
                                    <div class="symbol-checklist" id="autoResearchSymbolChecklist"></div>
                                </div>
                                <small>Có thể chọn nhiều cặp. Hệ thống sẽ chạy từng cặp riêng rồi tổng hợp kết quả.</small>
                            </label>
                            <label class="field">
                                <span>Chiến lược gốc</span>
                                <select id="autoResearchVersionSelect"></select>
                            </label>
                            <label class="field">
                                <span>Số vòng tối đa / mỗi cặp</span>
                                <input id="autoResearchMaxRounds" type="number" min="1" max="50" value="12">
                            </label>
                            <label class="field">
                                <span>Giới hạn giây / mỗi cặp</span>
                                <input id="autoResearchMaxSeconds" type="number" min="30" max="3600" value="300">
                            </label>
                            <label class="field">
                                <span>Tỷ lệ thắng tối thiểu (%)</span>
                                <input id="autoResearchWinRateMin" type="number" step="0.1" value="50">
                            </label>
                            <label class="field">
                                <span>RR trung bình tối thiểu</span>
                                <input id="autoResearchAverageRrMin" type="number" step="0.1" value="2">
                            </label>
                            <label class="field">
                                <span>Tổng lời / tổng lỗ tối thiểu</span>
                                <input id="autoResearchProfitFactorMin" type="number" step="0.1" value="1.5">
                            </label>
                            <label class="field">
                                <span>Sụt giảm vốn tối đa (%)</span>
                                <input id="autoResearchMaxDrawdownMax" type="number" step="0.1" value="10">
                            </label>
                            <label class="field">
                                <span>Số lệnh tối thiểu</span>
                                <input id="autoResearchTotalTradesMin" type="number" min="1" value="30">
                            </label>
                        </form>
                        <div class="notice" id="autoResearchStatus">Chọn chế độ và cặp tiền rồi bấm chạy. Hệ thống chỉ backtest và tạo bản nghiên cứu, không đặt lệnh MT5.</div>
                        <div class="auto-research-progress" id="autoResearchProgress" hidden>
                            <div class="auto-research-progress-head">
                                <strong id="autoResearchProgressTitle">Chưa chạy</strong>
                                <span id="autoResearchProgressPercent">0%</span>
                            </div>
                            <div class="auto-research-progress-track"><span id="autoResearchProgressFill"></span></div>
                            <div class="auto-research-progress-meta" id="autoResearchProgressMeta"></div>
                        </div>
                        <div class="auto-research-overview" id="autoResearchMetrics"></div>
                        <div class="method-tournament-board" id="autoResearchTournamentBoard"></div>
                        <div class="research-brain" id="autoResearchBrain"></div>
                        <div class="research-round-list" id="autoResearchRounds"></div>
                        <div class="research-trade-audit" id="autoResearchTradeAudit"></div>
                        <div class="research-run-list" id="autoResearchRuns"></div>
                    </section>
                </div>

                <section class="panel strategy-playbook-panel">
                    <details class="strategy-inline-section" id="strategyPlaybookDetails">
                        <summary>
                            <span>Tóm tắt phương pháp</span>
                            <small>Sổ chiến lược dạng dễ đọc: cặp tiền, H4/M15, mẫu vào lệnh, SL/TP/RR và cách thoát lệnh.</small>
                        </summary>
                        <div class="strategy-inline-body playbook is-embedded" id="strategyPlaybookContent"></div>
                    </details>
                </section>

                <section class="panel strategy-version-panel">
                    <div class="panel-header">
                        <h2>Lịch sử phiên bản</h2>
                    </div>
                    <div class="strategy-version-current" id="strategyVersionCompactSummary"></div>
                    <details class="strategy-version-manager" id="strategyVersionManager">
                        <summary>
                            <span>Mở quản lý phiên bản chi tiết</span>
                            <small>Danh sách, phê duyệt, kết quả test và dữ liệu kỹ thuật</small>
                        </summary>
                        <div class="strategy-version-manager-body">
                            <div class="topbar-actions strategy-version-manager-toolbar">
                                <button class="button" id="createStrategyV1Btn" type="button">Tạo chiến lược v1 từ bản hiện tại</button>
                                <button class="button secondary" id="refreshStrategyVersionsBtn" type="button">Tải lại phiên bản</button>
                            </div>
                            <div class="workspace-grid two">
                                <div class="strategy-version-list" id="strategyVersionList"></div>
                                <div class="strategy-version-detail" id="strategyVersionDetail"></div>
                            </div>
                        </div>
                    </details>
                </section>
            </section>

            <section class="tab-panel" id="tab-strategy-versions">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Phiên bản chiến lược</h2>
                            <div class="topbar-actions">
                                <button class="button secondary" id="newStrategyV2Btn" type="button">Tạo mới</button>
                                <button class="button secondary" id="refreshStrategyV2Btn" type="button">Tải lại</button>
                            </div>
                        </div>
                        <div class="strategy-v2-summary" id="strategyV2Summary"></div>
                        <div class="strategy-version-list" id="strategyV2List"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Hồ sơ phiên bản</h2>
                            <div class="topbar-actions">
                                <button class="button" id="createStrategyV2Btn" type="button">Tạo version</button>
                                <button class="button secondary" id="updateStrategyV2Btn" type="button">Cập nhật</button>
                                <button class="button secondary" id="cloneStrategyV2Btn" type="button">Clone</button>
                            </div>
                        </div>
                        <form id="strategyV2Form" class="form-grid">
                            <label class="field">
                                <span>Tên phiên bản</span>
                                <input id="strategyV2Name" type="text">
                            </label>
                            <label class="field">
                                <span>Mã version</span>
                                <input id="strategyV2Code" type="text">
                            </label>
                            <label class="field">
                                <span>Phiên bản cha</span>
                                <select id="strategyV2Parent"></select>
                            </label>
                            <label class="field">
                                <span>Trạng thái</span>
                                <select id="strategyV2Status">
                                    <option value="Draft">Bản nháp</option>
                                    <option value="Testing">Đang kiểm thử</option>
                                    <option value="Passed">Đạt</option>
                                    <option value="Rejected">Từ chối</option>
                                    <option value="Production">Production</option>
                                    <option value="Archived">Lưu trữ</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Nguồn tạo</span>
                                <select id="strategyV2CreatedBy">
                                    <option value="user">Người dùng</option>
                                    <option value="system">Hệ thống</option>
                                    <option value="ai">AI</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Mô tả</span>
                                <input id="strategyV2Description" type="text">
                            </label>
                            <label class="field full">
                                <span>Rules JSON</span>
                                <textarea id="strategyV2RulesJson" rows="8"></textarea>
                            </label>
                            <label class="field full">
                                <span>Settings JSON</span>
                                <textarea id="strategyV2SettingsJson" rows="5"></textarea>
                            </label>
                            <label class="field full">
                                <span>Performance JSON</span>
                                <textarea id="strategyV2PerformanceJson" rows="5"></textarea>
                            </label>
                        </form>
                    </section>
                </div>

                <section class="panel strategy-version-panel">
                    <div class="panel-header">
                        <h2>Chi tiết phiên bản</h2>
                        <div class="topbar-actions">
                            <button class="button" id="runStrategyV2BacktestBtn" type="button">Kiểm thử phiên bản này</button>
                            <button class="button secondary" id="approveStrategyV2Btn" type="button">Phê duyệt</button>
                            <button class="button success" id="promoteStrategyV2Btn" type="button">Production</button>
                            <button class="button danger" id="archiveStrategyV2Btn" type="button">Lưu trữ</button>
                        </div>
                    </div>
                    <div class="strategy-version-detail" id="strategyV2Detail"></div>
                </section>
            </section>

            <section class="tab-panel" id="tab-backtest">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Chạy kiểm thử chiến lược</h2>
                            <button class="button" id="runServerBacktestBtn" type="button">Bắt đầu kiểm thử</button>
                        </div>
                        <form class="form-grid" id="serverBacktestForm">
                            <label class="field full">
                                <span>Phiên bản chiến lược</span>
                                <select id="backtestVersionSelect"></select>
                            </label>
                            <label class="field">
                                <span>Cặp tiền</span>
                                <select id="backtestSymbolSelect"></select>
                            </label>
                            <label class="field">
                                <span>Phần dữ liệu</span>
                                <select id="backtestSplitSelect">
                                    <option value="train">Dữ liệu huấn luyện</option>
                                    <option value="validation">Dữ liệu xác thực</option>
                                    <option value="final">Dữ liệu kiểm tra cuối</option>
                                    <option value="custom">Khoảng tùy chọn</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Bộ thiết lập</span>
                                <select id="backtestPresetSelect">
                                    <option value="strict">Nghiêm ngặt</option>
                                    <option value="balanced" selected>Cân bằng</option>
                                    <option value="loose-debug">Nới lỏng để kiểm tra</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Mức thông tin kiểm tra</span>
                                <select id="backtestDebugLevelSelect">
                                    <option value="0">0 - Chỉ số tổng quan</option>
                                    <option value="1">1 - Chẩn đoán</option>
                                    <option value="2" selected>2 - Mẫu ứng viên</option>
                                    <option value="3">3 - Chi tiết từng bước</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Ngày bắt đầu tùy chọn</span>
                                <input id="backtestStartDate" type="date">
                            </label>
                            <label class="field">
                                <span>Ngày kết thúc tùy chọn</span>
                                <input id="backtestEndDate" type="date">
                            </label>
                            <label class="field">
                                <span>Số dư ban đầu</span>
                                <input id="backtestInitialBalance" type="number" value="10000">
                            </label>
                            <label class="field">
                                <span>Ghi đè spread (point)</span>
                                <input id="backtestSpread" type="number" placeholder="Để trống để dùng dữ liệu gốc">
                            </label>
                            <label class="field">
                                <span>Phí giao dịch (R)</span>
                                <input id="backtestFeeR" type="number" step="0.01" value="0.02">
                            </label>
                            <label class="field">
                                <span>Trượt giá (point)</span>
                                <input id="backtestSlippage" type="number" step="0.1" value="2">
                            </label>
                        </form>
                        <div class="notice" id="backtestDataQuality">Chưa chạy kiểm tra dữ liệu.</div>
                    </section>
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Kết quả kiểm thử</h2>
                            <span id="serverBacktestRunId"></span>
                        </div>
                        <div class="backtest-summary-grid" id="serverBacktestMetrics"></div>
                        <div class="backtest-diagnostics" id="serverBacktestDiagnostics"></div>
                        <div class="backtest-diagnostics" id="serverBacktestCandidates"></div>
                        <div class="backtest-trade-list server-trade-list" id="serverBacktestTrades"></div>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-backtest-v2">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Kết quả kiểm thử V2</h2>
                            <button class="button secondary" id="refreshBacktestV2Btn" type="button">Tải lại</button>
                        </div>
                        <form class="form-grid compact" id="backtestV2FilterForm">
                            <label class="field full">
                                <span>Phiên bản chiến lược</span>
                                <select id="backtestV2VersionFilter"></select>
                            </label>
                        </form>
                        <div class="backtest-v2-list" id="backtestV2ResultList"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Chi tiết kết quả</h2>
                            <span id="backtestV2RunId"></span>
                        </div>
                        <div id="backtestV2Detail" class="memory-summary"></div>
                        <div class="backtest-summary-grid" id="backtestV2Metrics"></div>

                        <section class="panel-subsection">
                            <div class="panel-header compact">
                                <h2>Đánh giá KPI</h2>
                                <span id="backtestV2EvaluationStatus"></span>
                            </div>
                            <div id="backtestV2Evaluation" class="memory-summary"></div>
                        </section>

                        <section class="panel-subsection">
                            <div class="panel-header compact">
                                <h2>Lý do đạt / không đạt</h2>
                            </div>
                            <div id="backtestV2Reasons" class="review-list"></div>
                        </section>

                        <section class="panel-subsection">
                            <div class="panel-header compact">
                                <h2>Khuyến nghị</h2>
                            </div>
                            <div id="backtestV2Recommendations" class="review-list"></div>
                        </section>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-kpi-profiles">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Cấu hình KPI</h2>
                            <button class="button secondary" id="refreshKPIProfilesBtn" type="button">Tải lại</button>
                        </div>
                        <div id="kpiProfileDefault" class="memory-summary"></div>
                        <div class="kpi-profile-list" id="kpiProfileList"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Hồ sơ KPI</h2>
                            <button class="button secondary" id="newKPIProfileBtn" type="button">Tạo mới</button>
                        </div>
                        <form class="form-grid" id="kpiProfileForm">
                            <label class="field full">
                                <span>Tên hồ sơ</span>
                                <input id="kpiProfileNameInput" type="text" placeholder="Ví dụ: KPI mặc định">
                            </label>
                            <label class="field full">
                                <span>Mô tả</span>
                                <textarea id="kpiProfileDescriptionInput" rows="2"></textarea>
                            </label>
                            <label class="field">
                                <span>Tỷ lệ thắng tối thiểu (%)</span>
                                <input id="kpiWinRateMinInput" type="number" step="0.01" min="0" max="100">
                            </label>
                            <label class="field">
                                <span>RR trung bình tối thiểu</span>
                                <input id="kpiAverageRRMinInput" type="number" step="0.01" min="0">
                            </label>
                            <label class="field">
                                <span>Tổng lời / tổng lỗ tối thiểu</span>
                                <input id="kpiProfitFactorMinInput" type="number" step="0.01" min="0">
                            </label>
                            <label class="field">
                                <span>Sụt giảm vốn tối đa (%)</span>
                                <input id="kpiMaxDrawdownMaxInput" type="number" step="0.01" min="0">
                            </label>
                            <label class="field">
                                <span>Số lệnh tối thiểu</span>
                                <input id="kpiTotalTradesMinInput" type="number" step="1" min="0">
                            </label>
                            <label class="field full">
                                <span>Ghi chú</span>
                                <textarea id="kpiProfileNotesInput" rows="3"></textarea>
                            </label>
                            <div class="field full">
                                <div class="topbar-actions">
                                    <button class="button" id="saveKPIProfileBtn" type="submit">Lưu hồ sơ</button>
                                    <button class="button secondary" id="setDefaultKPIProfileBtn" type="button">Đặt mặc định</button>
                                    <button class="button danger" id="deleteKPIProfileBtn" type="button">Xóa hồ sơ</button>
                                </div>
                            </div>
                        </form>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-experiments">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Thí nghiệm AI</h2>
                            <button class="button secondary" id="refreshExperimentsBtn" type="button">Tải lại</button>
                        </div>
                        <form class="form-grid" id="experimentCreateForm">
                            <label class="field full">
                                <span>Tên thí nghiệm</span>
                                <input id="experimentNameInput" type="text" placeholder="Ví dụ: Kiểm thử EURUSD H4/M15">
                            </label>
                            <label class="field full">
                                <span>Mục tiêu</span>
                                <textarea id="experimentGoalInput" rows="3" placeholder="Mục tiêu ngắn gọn cho lần nghiên cứu này"></textarea>
                            </label>
                            <label class="field full">
                                <span>Ghi chú</span>
                                <textarea id="experimentNotesInput" rows="3" placeholder="Ghi chú thêm nếu cần"></textarea>
                            </label>
                            <div class="field full">
                                <button class="button" id="createExperimentBtn" type="submit">Tạo thí nghiệm</button>
                            </div>
                        </form>
                        <div class="experiment-list" id="experimentList"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Chi tiết thí nghiệm</h2>
                            <span id="experimentDetailStatus"></span>
                        </div>
                        <div id="experimentDetail" class="memory-summary"></div>
                        <div class="topbar-actions experiment-actions" id="experimentActions">
                            <button class="button" data-experiment-action="start" type="button">Đang chạy</button>
                            <button class="button secondary" data-experiment-action="pause" type="button">Tạm dừng</button>
                            <button class="button secondary" data-experiment-action="complete" type="button">Hoàn thành</button>
                            <button class="button danger" data-experiment-action="archive" type="button">Lưu trữ</button>
                        </div>

                        <section class="panel-subsection">
                            <div class="panel-header compact">
                                <h2>Kết quả tốt nhất</h2>
                            </div>
                            <div id="experimentBestItem" class="memory-summary"></div>
                        </section>

                        <section class="panel-subsection">
                            <div class="panel-header compact">
                                <h2>Kết quả trong thí nghiệm</h2>
                                <span id="experimentItemCount">0</span>
                            </div>
                            <div class="experiment-items" id="experimentItems"></div>
                        </section>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-research-lab">
                <section class="panel research-lab-panel">
                    <div class="panel-header">
                        <div>
                            <h2>AI Lab - 2 AI làm việc với nhau</h2>
                            <span>Tạo một phiên để AI số 1 nghiên cứu chiến lược, AI số 2 backtest, rồi hai bên lặp lại đến khi đạt KPI.</span>
                        </div>
                        <div class="topbar-actions">
                            <button class="button secondary" id="refreshResearchLabBtn" type="button">Tải lại</button>
                            <button class="button secondary" id="runResearchLabOneBtn" type="button">Chạy 1 vòng</button>
                            <button class="button" id="runResearchLabLoopBtn" type="button">Chạy liên tục</button>
                            <button class="button danger" id="stopResearchLabBtn" type="button" hidden>Dừng sau vòng này</button>
                        </div>
                    </div>

                    <div class="auto-research-progress research-lab-progress" id="researchLabProgress" hidden>
                        <div class="auto-research-progress-head">
                            <strong id="researchLabProgressTitle">Chưa chạy</strong>
                            <span id="researchLabProgressPercent">0%</span>
                        </div>
                        <div class="auto-research-progress-track"><span id="researchLabProgressFill"></span></div>
                        <div class="auto-research-progress-meta" id="researchLabProgressMeta"></div>
                    </div>

                    <div class="research-lab-flow" aria-label="Luồng AI Lab">
                        <div class="research-lab-step research-lab-agent-card is-researcher">
                            <span class="research-lab-agent-badge">AI số 1</span>
                            <strong>Nhà nghiên cứu chiến lược</strong>
                            <span>Đọc KPI, sửa rule trong khung H4 trend + H4 zone + M15 Price Action. Không được tự bịa kết quả.</span>
                        </div>
                        <div class="research-lab-step research-lab-agent-card is-backtester">
                            <span class="research-lab-agent-badge">AI số 2</span>
                            <strong>Máy backtest & kiểm chứng</strong>
                            <span>Chạy engine backtest bằng dữ liệu nến thật trong app, trả summary, diagnostics và từng lệnh.</span>
                        </div>
                        <div class="research-lab-step research-lab-agent-card is-reviewer">
                            <span class="research-lab-agent-badge">Phản hồi</span>
                            <strong>AI số 1 nhận kết quả</strong>
                            <span>Đọc kết quả của AI số 2, giữ bản tốt nhất hoặc sửa phương pháp cho vòng tiếp theo.</span>
                        </div>
                    </div>

                    <form class="form-grid compact research-lab-form" id="researchLabForm">
                        <label class="field">
                            <span>Cặp tiền</span>
                            <select id="researchLabSymbolSelect"></select>
                        </label>
                        <label class="field">
                            <span>Phương pháp mẹ</span>
                            <select id="researchLabMethodSelect">
                                <option value="h4_trend_zone_m15_pa" selected>H4 Trend + H4 Zone + M15 Price Action</option>
                                <option value="pa_sd_h4_m15">Price Action + Vùng canh buy/sell H4/M15 (rộng)</option>
                                <option value="liquidity_sweep_pa">Liquidity sweep / false break</option>
                                <option value="trend_pullback">Pullback theo xu hướng</option>
                                <option value="range_sd_reversal">Range trading tại vùng canh buy/sell</option>
                                <option value="free_explore">Tự do khám phá nhiều họ chiến lược</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Nguồn AI số 1</span>
                            <select id="researchLabAiProviderSelect">
                                <option value="openai_chat" selected>OpenAI API - dùng key đã lưu</option>
                                <option value="internal">AI nội bộ - không tốn API</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Số vòng tối đa</span>
                            <input id="researchLabMaxCycles" type="number" min="1" max="100" value="20">
                        </label>
                        <label class="field">
                            <span>Winrate tối thiểu (%)</span>
                            <input id="researchLabWinRateMin" type="number" min="0" max="100" value="50">
                        </label>
                        <label class="field">
                            <span>RR trung bình tối thiểu</span>
                            <input id="researchLabAverageRrMin" type="number" min="0.1" step="0.1" value="2">
                        </label>
                        <label class="field">
                            <span>Profit factor tối thiểu</span>
                            <input id="researchLabProfitFactorMin" type="number" min="0" step="0.1" value="1.5">
                        </label>
                        <label class="field">
                            <span>Max DD tối đa (%)</span>
                            <input id="researchLabMaxDrawdownMax" type="number" min="0" step="0.1" value="10">
                        </label>
                        <label class="field">
                            <span>Số lệnh tối thiểu</span>
                            <input id="researchLabTotalTradesMin" type="number" min="1" value="50">
                        </label>
                        <label class="field">
                            <span>Max bar giữ lệnh</span>
                            <input id="researchLabMaxBarsInTrade" type="number" min="5" value="96">
                        </label>
                    </form>
                    <div class="topbar-actions research-lab-create-row">
                        <button class="button" id="createResearchLabBtn" type="button">Tạo phiên 2 AI mới</button>
                        <span id="researchLabStatus">Chưa có phiên AI Lab.</span>
                    </div>
                </section>

                <div class="workspace-grid two research-lab-workspace">
                    <section class="panel">
                        <div class="panel-header compact">
                            <h2>Danh sách phiên</h2>
                            <span id="researchLabSessionCount">0 phiên</span>
                        </div>
                        <div class="research-lab-session-list" id="researchLabSessions"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-header compact">
                            <h2>Chi tiết phiên</h2>
                            <span id="researchLabDetailHint">Chọn hoặc tạo một phiên để xem vòng lặp.</span>
                        </div>
                        <div class="research-lab-detail" id="researchLabDetail"></div>
                    </section>
                </div>
            </section>
            <section class="tab-panel" id="tab-ai-orchestrator">
                <details class="advanced-tools">
                    <summary class="advanced-tools-summary">
                        <span>Công cụ kỹ thuật: AI job thủ công / AI Orchestrator</span>
                        <small>Dùng khi cần debug prompt, worker hoặc tạo job riêng.</small>
                    </summary>
                    <div class="advanced-tools-body">
                        <div class="workspace-grid two">
                            <section class="panel">
                                <div class="panel-header">
                                    <h2>Tạo AI job thủ công</h2>
                                    <button class="button secondary" id="refreshAIJobsBtn" type="button">Tải lại</button>
                                </div>
                                <form class="form-grid" id="aiJobCreateForm">
                                    <label class="field">
                                        <span>Loại job</span>
                                        <select id="aiJobTypeInput">
                                            <option value="GenerateStrategyIdea">Ý tưởng chiến lược</option>
                                            <option value="ImproveStrategy">Cải thiện chiến lược</option>
                                            <option value="AnalyzeBacktest">Phân tích backtest</option>
                                            <option value="EvaluateExperiment">Đánh giá thí nghiệm</option>
                                        </select>
                                    </label>
                                    <label class="field">
                                        <span>Phiên bản chiến lược</span>
                                        <select id="aiJobStrategyVersionInput"></select>
                                    </label>
                                    <label class="field">
                                        <span>Thí nghiệm</span>
                                        <select id="aiJobExperimentInput"></select>
                                    </label>
                                    <label class="field">
                                        <span>Người tạo</span>
                                        <input id="aiJobCreatedByInput" type="text" value="user">
                                    </label>
                                    <label class="field full">
                                        <span>Input JSON</span>
                                        <textarea id="aiJobInputJson" rows="7">{}</textarea>
                                    </label>
                                    <label class="field full">
                                        <span>Ghi chú</span>
                                        <textarea id="aiJobNotesInput" rows="3" placeholder="Ghi chú thủ công cho job này"></textarea>
                                    </label>
                                    <div class="field full">
                                        <button class="button" type="submit">Tạo AI job</button>
                                    </div>
                                </form>
                            </section>

                            <section class="panel">
                                <div class="panel-header">
                                    <h2>Danh sách AI jobs</h2>
                                    <div class="topbar-actions">
                                        <button class="button" id="runResearchMockOnceBtn" type="button">Chạy nghiên cứu thử</button>
                                        <button class="button secondary" id="runAIWorkerOnceBtn" type="button">Chạy worker thử</button>
                                        <span id="aiJobCount">0</span>
                                    </div>
                                </div>
                                <div class="ai-job-list" id="aiJobList"></div>
                            </section>
                        </div>

                        <section class="panel strategy-version-panel">
                            <div class="panel-header">
                                <h2>Chi tiết AI job</h2>
                                <div class="topbar-actions">
                                    <button class="button" id="createAIJobStrategyDraftBtn" type="button" hidden>Tạo bản nháp chiến lược</button>
                                    <button class="button danger" id="cancelAIJobBtn" type="button">Hủy job đang chờ</button>
                                </div>
                            </div>
                            <div class="strategy-version-detail" id="aiJobDetail"></div>
                        </section>
                    </div>
                </details>
            </section>

            <section class="tab-panel" id="tab-openai-settings">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <div>
                                <h2>Trạng thái OpenAI</h2>
                                <span>Kiểm tra API key, model, ngân sách ngày và chi phí đã dùng.</span>
                            </div>
                            <button class="button secondary" id="refreshOpenAiSettingsBtn" type="button">Tải lại</button>
                        </div>
                        <div class="strategy-v2-summary" id="openAiSettingsSummary"></div>
                        <div class="notice">
                            OpenAI chỉ dùng để phân tích và đề xuất hướng nghiên cứu. Backtest, KPI và quyết định đạt/chưa đạt vẫn do code trong app tính.
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <div>
                                <h2>Cấu hình OpenAI API</h2>
                                <span>Key được lưu local trong .env.local, không hiện lại trên giao diện.</span>
                            </div>
                            <button class="button" id="saveOpenAiSettingsBtn" type="submit" form="openAiSettingsForm">Lưu cấu hình</button>
                        </div>
                        <form id="openAiSettingsForm" class="form-grid">
                            <label class="field full">
                                <span>Bật OpenAI API</span>
                                <div class="check-row">
                                    <label><input id="openAiEnabledInput" type="checkbox" checked> Cho phép app gọi OpenAI khi bạn chọn OpenAI API</label>
                                </div>
                            </label>
                            <div class="field full">
                                <span>API key đã lưu</span>
                                <div class="openai-key-card" id="openAiKeyCard"></div>
                                <div class="topbar-actions">
                                    <button class="button secondary" id="editOpenAiKeyBtn" type="button">Thêm / đổi key</button>
                                    <button class="button danger" id="deleteOpenAiKeyBtn" type="button">Xóa key</button>
                                </div>
                            </div>
                            <label class="field full">
                                <span>Dán API key mới</span>
                                <input id="openAiApiKeyInput" type="password" autocomplete="off" placeholder="sk-...">
                                <small id="openAiApiKeyHint">Để trống nếu muốn giữ key đã lưu. Dán key mới vào đây rồi bấm Lưu cấu hình để thêm hoặc thay key.</small>
                            </label>
                            <label class="field">
                                <span>Model mặc định</span>
                                <input id="openAiModelInput" type="text" value="gpt-6-luna">
                            </label>
                            <label class="field">
                                <span>Ngân sách tối đa / ngày (USD)</span>
                                <input id="openAiDailyBudgetInput" type="number" min="0" step="0.01" value="1">
                            </label>
                            <label class="field">
                                <span>Giới hạn output token / lần gọi</span>
                                <input id="openAiMaxTokensInput" type="number" min="64" max="32000" step="1" value="1200">
                            </label>
                            <label class="field">
                                <span>Số lần gọi tối đa / ngày</span>
                                <input id="openAiMaxCallsInput" type="number" min="0" max="100000" step="1" value="100">
                            </label>
                            <div class="field full">
                                <button class="button secondary" id="testOpenAiSettingsBtn" type="button">Test OpenAI</button>
                            </div>
                        </form>
                        <div class="strategy-version-detail" id="openAiTestResult"></div>

                        <div class="openai-billing-panel">
                            <div class="panel-header">
                                <div>
                                    <h2>Chi phí chính thức OpenAI</h2>
                                    <span>Dùng Admin API key để lấy Costs API của organization.</span>
                                </div>
                                <button class="button secondary" id="syncOpenAiOfficialCostsBtn" type="button">Đồng bộ chi phí</button>
                            </div>
                            <div class="field full">
                                <span>Admin API key đã lưu</span>
                                <div class="openai-key-card" id="openAiAdminKeyCard"></div>
                                <div class="topbar-actions">
                                    <button class="button secondary" id="editOpenAiAdminKeyBtn" type="button">Thêm / đổi admin key</button>
                                    <button class="button danger" id="deleteOpenAiAdminKeyBtn" type="button">Xóa admin key</button>
                                </div>
                            </div>
                            <label class="field full">
                                <span>Dán Admin API key mới</span>
                                <input id="openAiAdminKeyInput" type="password" autocomplete="off" placeholder="sk-admin-...">
                                <small id="openAiAdminKeyHint">Admin key chỉ dùng để đọc Costs API. Không dùng key này để chạy AI.</small>
                            </label>
                            <div class="topbar-actions">
                                <button class="button" id="saveOpenAiAdminKeyBtn" type="button">Lưu admin key</button>
                            </div>
                            <div class="strategy-v2-summary" id="openAiOfficialCostSummary"></div>
                            <div class="notice">
                                Costs API là số chính thức theo bucket ngày UTC và có thể có độ trễ. Hàng rào chặn chi phí realtime vẫn dùng số app tự ước tính.
                            </div>
                        </div>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-prompt-ai">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Prompt AI</h2>
                            <div class="topbar-actions">
                                <button class="button secondary" id="newPromptTemplateBtn" type="button">Tạo mới</button>
                                <button class="button secondary" id="refreshPromptTemplatesBtn" type="button">Tải lại</button>
                            </div>
                        </div>
                        <div class="strategy-v2-summary" id="promptTemplateSummary"></div>
                        <div class="strategy-version-list" id="promptTemplateList"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Biểu mẫu prompt</h2>
                            <div class="topbar-actions">
                                <button class="button" id="savePromptTemplateBtn" type="submit" form="promptTemplateForm">Lưu prompt</button>
                                <button class="button secondary" id="activatePromptTemplateBtn" type="button">Kích hoạt</button>
                                <button class="button danger" id="archivePromptTemplateBtn" type="button">Lưu trữ</button>
                            </div>
                        </div>
                        <form id="promptTemplateForm" class="form-grid">
                            <label class="field">
                                <span>ID</span>
                                <input id="promptTemplateIdInput" type="text" readonly>
                            </label>
                            <label class="field">
                                <span>Template key</span>
                                <input id="promptTemplateKeyInput" type="text" placeholder="strategy.idea.generate">
                            </label>
                            <label class="field">
                                <span>Version</span>
                                <input id="promptTemplateVersionInput" type="number" min="1" step="1" value="1">
                            </label>
                            <label class="field">
                                <span>Trạng thái</span>
                                <select id="promptTemplateStatusInput">
                                    <option value="Draft">Draft</option>
                                    <option value="Review">Review</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Active">Active</option>
                                    <option value="Deprecated">Deprecated</option>
                                    <option value="Archived">Archived</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Tên</span>
                                <input id="promptTemplateNameInput" type="text">
                            </label>
                            <label class="field">
                                <span>Module sở hữu</span>
                                <input id="promptTemplateOwnerInput" type="text" placeholder="StrategyAI">
                            </label>
                            <label class="field">
                                <span>Output format</span>
                                <select id="promptTemplateOutputFormatInput">
                                    <option value="plain_text">plain_text</option>
                                    <option value="json_object">json_object</option>
                                    <option value="json_array">json_array</option>
                                    <option value="schema_bound">schema_bound</option>
                                    <option value="markdown_report">markdown_report</option>
                                    <option value="code_review_report">code_review_report</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Parser mode</span>
                                <select id="promptTemplateParserModeInput">
                                    <option value="plain_text">plain_text</option>
                                    <option value="json_object">json_object</option>
                                    <option value="json_array">json_array</option>
                                    <option value="schema_bound">schema_bound</option>
                                    <option value="markdown_report">markdown_report</option>
                                    <option value="code_review_report">code_review_report</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Mức an toàn</span>
                                <input id="promptTemplateSafetyInput" type="text" value="normal">
                            </label>
                            <label class="field full">
                                <span>Mô tả</span>
                                <textarea id="promptTemplateDescriptionInput" rows="2"></textarea>
                            </label>
                            <label class="field full">
                                <span>System prompt</span>
                                <textarea id="promptTemplateSystemInput" rows="4"></textarea>
                            </label>
                            <label class="field full">
                                <span>Developer prompt</span>
                                <textarea id="promptTemplateDeveloperInput" rows="4"></textarea>
                            </label>
                            <label class="field full">
                                <span>User prompt template</span>
                                <textarea id="promptTemplateUserInput" rows="7" placeholder="Dùng biến dạng {{ten_bien}}"></textarea>
                            </label>
                            <label class="field full">
                                <span>Biến bắt buộc</span>
                                <input id="promptTemplateRequiredInput" type="text" placeholder="research_goal, strategy_context">
                            </label>
                            <label class="field full">
                                <span>Biến tùy chọn</span>
                                <input id="promptTemplateOptionalInput" type="text" placeholder="memory_lessons">
                            </label>
                            <label class="field full">
                                <span>Ghi chú</span>
                                <textarea id="promptTemplateNotesInput" rows="3"></textarea>
                            </label>
                        </form>
                    </section>
                </div>
                <section class="panel strategy-version-panel" id="promptGatewayTestPanel">
                    <div class="panel-header">
                        <h2>Mock AI test</h2>
                        <div class="topbar-actions">
                            <button class="button" id="testPromptMockAiBtn" type="button">Test prompt bằng Mock AI</button>
                        </div>
                    </div>
                    <div class="strategy-v2-summary" id="promptGatewayTestSummary"></div>
                    <div class="form-grid compact">
                        <label class="field">
                            <span>Provider</span>
                            <select id="promptGatewayProviderSelect">
                                <option value="mock">Mock</option>
                                <option value="openai_chat">OpenAI Chat</option>
                                <option value="ollama">Ollama Local</option>
                            </select>
                        </label>
                        <label class="field full">
                            <span>Biến mẫu JSON</span>
                            <textarea id="promptGatewayVariablesInput" rows="8"></textarea>
                        </label>
                    </div>
                    <div class="strategy-version-detail" id="promptGatewayTestResult"></div>
                </section>
            </section>

            <section class="tab-panel" id="tab-calibration">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Hàng chờ hiệu chỉnh</h2>
                            <button class="button" id="loadCalibrationQueueBtn" type="button">Tải danh sách</button>
                        </div>
                        <form class="form-grid" id="calibrationQueueForm">
                            <label class="field full">
                                <span>Phiên bản chiến lược</span>
                                <select id="calibrationVersionSelect"></select>
                            </label>
                            <label class="field">
                                <span>Cặp tiền</span>
                                <select id="calibrationSymbolSelect"></select>
                            </label>
                            <label class="field">
                                <span>Phần dữ liệu</span>
                                <select id="calibrationSplitSelect">
                                    <option value="train">Dữ liệu huấn luyện</option>
                                    <option value="validation">Dữ liệu xác thực</option>
                                    <option value="final">Dữ liệu kiểm tra cuối</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Bộ thiết lập</span>
                                <select id="calibrationPresetSelect"></select>
                            </label>
                            <label class="field">
                                <span>Loại hàng chờ</span>
                                <select id="calibrationQueueTypeSelect">
                                    <option value="ZONE_ALREADY_BROKEN">Vùng đã bị phá trước đó</option>
                                    <option value="WEAK_H4_ZONE">Vùng H4 quá yếu</option>
                                    <option value="NO_M15_CONFIRMATION">Chưa có xác nhận M15</option>
                                    <option value="DIRTY_SIDEWAY">Thị trường đi ngang nhiễu</option>
                                    <option value="BAD_RR">Tỷ lệ RR không đạt</option>
                                    <option value="LIMIT_NOT_FILLED">Lệnh chờ không được khớp</option>
                                    <option value="TRADES_ENTERED">Các lệnh đã vào</option>
                                    <option value="ZONES_LIFECYCLE">Vòng đời vùng giá</option>
                                </select>
                            </label>
                            <label class="field">
                                <span>Số lượng mẫu</span>
                                <input id="calibrationLimitInput" type="number" min="1" max="500" value="50">
                            </label>
                        </form>
                        <div class="calibration-progress" id="calibrationProgress">Đã xem 0 / 0</div>
                        <div class="review-list calibration-queue-list" id="calibrationQueueList"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Mục đang xem</h2>
                            <div class="case-nav">
                                <button class="icon-button" id="calibrationPrevBtn" type="button">Trước</button>
                                <span id="calibrationPosition">0 / 0</span>
                                <button class="icon-button" id="calibrationNextBtn" type="button">Sau</button>
                            </div>
                        </div>
                        <div id="calibrationItemDetail"></div>
                    </section>
                </div>

                <section class="panel calibration-report-panel">
                    <div class="panel-header">
                        <h2>Báo cáo và gợi ý hiệu chỉnh</h2>
                        <div class="topbar-actions">
                            <button class="button secondary" id="loadCalibrationReportBtn" type="button">Tạo báo cáo</button>
                            <button class="button secondary" id="loadCalibrationAdvisorBtn" type="button">Gợi ý điều chỉnh luật</button>
                            <button class="button" id="generateCalibratedPresetBtn" type="button">Tạo bộ thiết lập đã hiệu chỉnh</button>
                        </div>
                    </div>
                    <div class="workspace-grid two">
                        <div id="calibrationReportOutput" class="memory-summary"></div>
                        <div id="calibrationAdvisorOutput" class="review-list"></div>
                    </div>
                </section>
            </section>

            <section class="tab-panel" id="tab-evolution">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>TradeBrain tiến hóa chiến lược</h2>
                            <button class="button" id="runEvolutionBtn" type="button">TradeBrain nâng cấp chiến lược</button>
                        </div>
                        <form class="form-grid" id="evolutionForm">
                            <label class="field full">
                                <span>Chiến lược gốc</span>
                                <select id="evolutionParentSelect"></select>
                            </label>
                            <label class="field">
                                <span>Cặp tiền</span>
                                <select id="evolutionSymbolSelect"></select>
                            </label>
                            <label class="field">
                                <span>Số dư ban đầu</span>
                                <input id="evolutionInitialBalance" type="number" value="10000">
                            </label>
                        </form>
                        <div class="notice">
                            Quy trình này chỉ kiểm thử trên dữ liệu mô phỏng. Không giao dịch thật và không gửi lệnh qua MT5.
                        </div>
                    </section>
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Đề xuất và kết quả đạt/không đạt</h2>
                            <span id="evolutionStatus"></span>
                        </div>
                        <div class="evolution-output" id="evolutionOutput"></div>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-compare">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>So sánh phiên bản</h2>
                            <button class="button secondary" id="refreshCompareBtn" type="button">Tải lại</button>
                        </div>
                        <div id="strategyCompareList" class="compare-list"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Khác biệt giữa các bộ luật</h2>
                            <span id="strategyDiffCount"></span>
                        </div>
                        <div id="strategyDiffView"></div>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-playbook">
                <div class="playbook" id="playbookContent"></div>
            </section>

            <section class="tab-panel" id="tab-mt5">
                <div class="workspace-grid two mt5-layout">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Kết nối MT5 Exness</h2>
                            <button class="button secondary" id="mt5StatusBtn" type="button">Kiểm tra kết nối</button>
                        </div>
                        <div class="notice">
                            Ứng dụng đọc dữ liệu đã lưu trong thư mục data/market_data. Chỉ mở MT5 khi cần kiểm tra kết nối hoặc cập nhật dữ liệu.
                        </div>
                        <form class="form-grid mt5-form" id="mt5Form">
                            <label class="field full">
                                <span>Đường dẫn terminal64.exe nếu cần</span>
                                <input id="mt5TerminalPath" type="text" placeholder="Có thể để trống nếu MT5 đang mở">
                                <small>Chỉ nhập nếu app không tự tìm thấy MT5 đang mở.</small>
                            </label>
                            <label class="field full">
                                <span>Cặp cần lấy dữ liệu</span>
                                <textarea id="mt5Symbols" rows="4"></textarea>
                            </label>
                            <label class="field">
                                <span>S&#7889; n&#7871;n M15 m&#7909;c ti&#234;u</span>
                                <input id="mt5Bars" type="number" value="100000" min="50" max="100000">
                                <small id="mt5BarsHint">M15 l&#224; m&#7889;c ch&#237;nh, t&#7889;i &#273;a 100,000 n&#7871;n. C&#225;c khung kh&#225;c t&#7921; quy &#273;&#7893;i theo c&#249;ng kho&#7843;ng th&#7901;i gian.</small>
                            </label>
                            <label class="field">
                                <span>Khung thời gian</span>
                                <div class="check-row">
                                    <label><input type="checkbox" data-mt5-timeframe="M1"> M1</label>
                                    <label><input type="checkbox" data-mt5-timeframe="M5"> M5</label>
                                    <label><input type="checkbox" data-mt5-timeframe="H4" checked> H4</label>
                                    <label><input type="checkbox" data-mt5-timeframe="M15" checked> M15</label>
                                    <label><input type="checkbox" data-mt5-timeframe="M30"> M30</label>
                                    <label><input type="checkbox" data-mt5-timeframe="H1"> H1</label>
                                    <label><input type="checkbox" data-mt5-timeframe="D1"> D1</label>
                                </div>
                            </label>
                        </form>
                        <div class="topbar-actions mt5-actions">
                            <button class="button" id="mt5FetchBtn" type="button">Lấy dữ liệu từ MT5</button>
                            <button class="button secondary" id="mt5RefreshFilesBtn" type="button">Tải lại danh sách tệp</button>
                        </div>
                        <div class="mt5-status" id="mt5Status"></div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <h2>Dữ liệu đã lưu</h2>
                            <span id="marketFileCount">0</span>
                        </div>
                        <div class="market-file-list" id="marketFileList"></div>
                    </section>
                </div>

                <div id="mt5ChartMount" class="shared-market-chart-mount">
                <section class="panel market-chart-panel" id="marketChartPanel">
                    <div class="chart-topbar">
                        <div>
                            <h2 id="marketChartTitle">Biểu đồ dữ liệu MT5</h2>
                            <span id="marketChartMeta">Chưa chọn tệp</span>
                        </div>
                        <div class="chart-controls">
                            <div class="chart-symbol-group" aria-label="Cặp tiền đang xem">
                                <select id="chartSymbolSelect" aria-label="Mã đang xem"></select>
                                <input id="chartCompareInput" type="text" placeholder="Nhập cặp tiền để so sánh">
                                <button class="chart-control" id="chartAddCompareBtn" type="button">So sánh</button>
                                <button class="chart-control" id="chartClearCompareBtn" type="button">Bỏ so sánh</button>
                            </div>
                            <div class="chart-timeframe-controls" aria-label="Khung thời gian">
                                <button class="chart-control chart-timeframe-main is-active" data-chart-timeframe="H4" type="button">H4</button>
                                <button class="chart-control chart-timeframe-main" data-chart-timeframe="M15" type="button">M15</button>
                                <button class="chart-control chart-timeframe-jump" id="chartSelectionToM15Btn" type="button" title="Dùng công cụ Mũi tên / chọn (□) kéo khoanh vùng trước, rồi bấm để mở đúng khoảng thời gian trên M15">Vùng → M15</button>
                                <button class="chart-control chart-timeframe-jump" id="chartSelectionToH1Btn" type="button" title="Dùng công cụ Mũi tên / chọn (□) kéo khoanh vùng trước, rồi bấm để mở đúng khoảng thời gian trên H1">Vùng → H1</button>
                                <div class="chart-timeframe-more" id="chartTimeframeMore">
                                    <button class="chart-control" id="chartTimeframeMoreToggle" type="button" title="Mở các khung thời gian khác" aria-expanded="false" aria-controls="chartTimeframeMenu">Khác <span aria-hidden="true">⌄</span></button>
                                    <div class="chart-timeframe-menu" id="chartTimeframeMenu" aria-label="Khung thời gian khác" hidden>
                                        <button class="chart-control" data-chart-timeframe="M1" type="button">M1</button>
                                        <button class="chart-control" data-chart-timeframe="M5" type="button">M5</button>
                                        <button class="chart-control" data-chart-timeframe="M30" type="button">M30</button>
                                        <button class="chart-control" data-chart-timeframe="H1" type="button">H1</button>
                                        <button class="chart-control" data-chart-timeframe="D1" type="button">D1</button>
                                    </div>
                                </div>
                            </div>
                            <div class="chart-view-controls" aria-label="Điều khiển biểu đồ">
                                <button class="chart-control" id="chartResetBtn" type="button">Đặt lại</button>
                                <button class="chart-control" id="chartFitBtn" type="button" title="Căn vừa cả chiều ngang và chiều dọc"><span class="chart-label-full">Vừa</span><span class="chart-label-compact" aria-hidden="true">⤢</span></button>
                                <button class="chart-control" id="chartFitHorizontalBtn" type="button" title="Căn biểu đồ vừa theo chiều ngang"><span class="chart-label-full">Ngang</span><span class="chart-label-compact" aria-hidden="true">↔</span></button>
                                <button class="chart-control" id="chartFitVerticalBtn" type="button" title="Căn biểu đồ vừa theo chiều dọc"><span class="chart-label-full">Dọc</span><span class="chart-label-compact" aria-hidden="true">↕</span></button>
                                <button class="chart-control chart-jump-control" id="chartJumpStartBtn" type="button" title="Về cây nến đầu tiên">|&lt;</button>
                                <button class="chart-control chart-jump-control" id="chartJumpEndBtn" type="button" title="Về cây nến mới nhất">&gt;|</button>
                                <button class="chart-control" id="chartScreenshotBtn" type="button">Chụp ảnh</button>
                                <button class="chart-control" id="chartThemeBtn" type="button" title="Bật/tắt nền tối"><span class="chart-label-full">Tối</span><span class="chart-label-compact" aria-hidden="true">◐</span></button>
                                <button class="chart-control" id="chartFullscreenBtn" type="button" title="Phóng to biểu đồ"><span class="chart-label-full">Full</span><span class="chart-label-compact" aria-hidden="true">⛶</span></button>
                            </div>
                            <button class="chart-control pa-replay-toggle-control" id="chartReplayPanelToggleBtn" type="button" title="Bật/tắt thanh tua nến">▣ Tua nến</button>
                        </div>
                    </div>
                    <div class="chart-feature-bar" id="chartFeatureBar">
                        <div class="chart-feature-group">
                            <strong>Biểu đồ</strong>
                            <select id="chartTypeSelect" aria-label="Kiểu biểu đồ">
                                <option value="candles">Nến</option>
                                <option value="bars">Thanh giá</option>
                                <option value="line">Đường</option>
                                <option value="area">Vùng</option>
                                <option value="heikin">Heikin Ashi</option>
                                <option value="renko">Renko</option>
                            </select>
                            <select id="chartMagnetMode" aria-label="Chế độ nam châm">
                                <option value="off">Tắt nam châm</option>
                                <option value="weak">Nam châm yếu</option>
                                <option value="strong">Nam châm mạnh</option>
                            </select>
                            <label><input type="checkbox" id="chartStayDrawingToggle"> Vẽ liên tục</label>
                            <label><input type="checkbox" id="chartLockToggle"> Khóa hình vẽ</label>
                            <label><input type="checkbox" id="chartHideDrawingsToggle"> Ẩn hình vẽ</label>
                            <label><input type="checkbox" id="chartFavoritesToggle" checked> Công cụ yêu thích</label>
                            <button class="chart-control" id="chartUndoBtn" type="button">Hoàn tác</button>
                            <button class="chart-control" id="chartRedoBtn" type="button">Làm lại</button>
                            <button class="chart-control" id="chartExportDrawingsBtn" type="button">Xuất hình vẽ</button>
                            <button class="chart-control" id="chartImportDrawingsBtn" type="button">Nhập hình vẽ</button>
                            <input id="chartDrawingImport" type="file" accept="application/json" hidden>
                        </div>
                        <div class="chart-feature-group">
                            <strong>Chỉ báo</strong>
                            <label><input type="checkbox" data-chart-toggle="ema20" checked> EMA 20</label>
                            <label><input type="checkbox" data-chart-toggle="ema50" checked> EMA 50</label>
                            <label><input type="checkbox" data-chart-toggle="ema200"> EMA 200</label>
                            <label><input type="checkbox" data-chart-toggle="sma100"> SMA 100</label>
                            <label><input type="checkbox" data-chart-toggle="bollinger"> Bollinger</label>
                            <label><input type="checkbox" data-chart-toggle="vwap"> VWAP</label>
                            <label><input type="checkbox" data-chart-toggle="atrStop"> Dừng ATR</label>
                            <label><input type="checkbox" data-chart-toggle="rsi"> RSI</label>
                            <label><input type="checkbox" data-chart-toggle="macd"> MACD</label>
                            <select id="chartIndicatorTemplate" aria-label="Mẫu chỉ báo">
                                <option value="default">Mẫu mặc định</option>
                                <option value="clean">Biểu đồ sạch</option>
                                <option value="trend">Xu hướng EMA</option>
                                <option value="momentum">Động lượng</option>
                            </select>
                            <button class="chart-control" id="chartSaveIndicatorTemplateBtn" type="button">Lưu mẫu</button>
                            <button class="chart-control" id="chartAlertBtn" type="button">Cảnh báo</button>
                        </div>
                        <div class="chart-feature-group">
                            <strong>Kiểm thử</strong>
                            <label><input type="checkbox" data-chart-toggle="markers" checked> Vào/thoát</label>
                            <label><input type="checkbox" data-chart-toggle="slTp" checked> SL/TP</label>
                            <label><input type="checkbox" data-chart-toggle="positionBands" checked> Vùng giữ lệnh</label>
                            <label><input type="checkbox" data-chart-toggle="warnings" checked> Cảnh báo dữ liệu</label>
                            <select id="chartRunSelect" aria-label="Bộ kiểm thử"></select>
                            <select id="chartTradeFilter" aria-label="Lọc lệnh">
                                <option value="all">Tất cả lệnh</option>
                                <option value="win">Lệnh thắng</option>
                                <option value="loss">Lệnh thua</option>
                                <option value="long">Lệnh mua</option>
                                <option value="short">Lệnh bán</option>
                            </select>
                            <button class="chart-control" id="chartPrevTradeBtn" type="button">Lệnh trước</button>
                            <button class="chart-control" id="chartNextTradeBtn" type="button">Lệnh sau</button>
                        </div>
                        <div class="chart-feature-group replay-group">
                            <div class="replay-segment replay-navigation-segment">
                                <strong class="replay-segment-label" title="Chạy nến để backtest thủ công"><span aria-hidden="true">◷</span>Nến</strong>
                                <div class="chart-replay-buttons" aria-label="Điều khiển chạy nến">
                                <button class="chart-control" id="chartReplayBackBtn" type="button" title="Lùi một nến">‹</button>
                                <button class="chart-control" id="chartReplayPlayBtn" type="button" title="Cho nến chạy / tạm dừng">▶</button>
                                <button class="chart-control" id="chartReplayStepBtn" type="button" title="Tiến một nến">›</button>
                                </div>
                            </div>
                            <div class="replay-segment replay-speed-segment">
                                <strong class="replay-segment-label"><span aria-hidden="true">⚡</span>Tốc độ</strong>
                                <div class="chart-replay-speed" aria-label="Tốc độ chạy nến">
                                    <button class="chart-control" id="chartReplaySlowerBtn" type="button" title="Chạy chậm hơn">−</button>
                                    <select id="chartReplaySpeed" aria-label="Tốc độ phát lại">
                                        <option value="0.5">0.5x</option>
                                        <option value="1">1x</option>
                                        <option value="2">2x</option>
                                        <option value="4">4x</option>
                                        <option value="8">8x</option>
                                        <option value="16">16x</option>
                                    </select>
                                    <button class="chart-control" id="chartReplayFasterBtn" type="button" title="Chạy nhanh hơn">+</button>
                                </div>
                            </div>
                            <div class="replay-segment replay-progress-segment">
                                <strong class="replay-segment-label"><span aria-hidden="true">▰</span>Tiến độ</strong>
                                <div class="replay-progress-track">
                                    <input id="chartReplayRange" type="range" min="0" max="0" value="0" aria-label="Vị trí nến đang xem">
                                    <span id="chartReplayLabel">Chưa có dữ liệu</span>
                                </div>
                            </div>
                        </div>
                        <div class="chart-feature-group">
                            <strong>Bố cục</strong>
                            <select id="chartLayoutSelect" aria-label="Bố cục biểu đồ"></select>
                            <button class="chart-control" id="chartSaveLayoutBtn" type="button">Lưu bố cục</button>
                            <button class="chart-control" id="chartLoadLayoutBtn" type="button">Tải bố cục</button>
                            <button class="chart-control" id="chartMultiLayoutBtn" type="button">1 biểu đồ</button>
                            <input id="chartQuickSearchInput" type="text" placeholder="Tìm nhanh cặp tiền hoặc ngày">
                            <button class="chart-control" id="chartQuickSearchBtn" type="button">Tìm</button>
                            <button class="chart-control" id="chartSettingsBtn" type="button">Cài đặt</button>
                        </div>
                    </div>
                    <div class="chart-workspace">
                        <div class="drawing-toolbar" aria-label="Công cụ vẽ">
                            <button class="drawing-tool is-active" data-chart-tool="cursor" data-tool-favorite="1" title="Tâm ngắm / kéo biểu đồ" type="button">+</button>
                            <button class="drawing-tool" data-chart-tool="dot" title="Con trỏ dạng chấm" type="button">·</button>
                            <button class="drawing-tool" data-chart-tool="select" data-tool-favorite="1" title="Mũi tên / chọn" type="button">□</button>
                            <button class="drawing-tool" data-chart-tool="erase" title="Tẩy hình vẽ" type="button">⌫</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="trendline" title="Vẽ đường xu hướng" type="button">╱</button>
                            <button class="drawing-tool" data-chart-tool="ray" title="Vẽ tia xu hướng" type="button">↗</button>
                            <button class="drawing-tool" data-chart-tool="extended-line" title="Đường kéo dài hai phía" type="button">⟷</button>
                            <button class="drawing-tool" data-chart-tool="hline" title="Vẽ đường ngang" type="button">─</button>
                            <button class="drawing-tool" data-chart-tool="hray" title="Tia ngang" type="button">↦</button>
                            <button class="drawing-tool" data-chart-tool="vline" title="Vẽ đường dọc theo thời gian" type="button">│</button>
                            <button class="drawing-tool" data-chart-tool="cross-line" title="Đường chữ thập" type="button">⌖</button>
                            <button class="drawing-tool" data-chart-tool="trend-angle" title="Góc xu hướng" type="button">∠</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="parallel-channel" title="Kênh song song" type="button">∥</button>
                            <button class="drawing-tool" data-chart-tool="regression-trend" title="Xu hướng hồi quy" type="button">R</button>
                            <button class="drawing-tool" data-chart-tool="flat-top-bottom" title="Kênh đỉnh hoặc đáy ngang" type="button">⊓</button>
                            <button class="drawing-tool" data-chart-tool="disjoint-channel" title="Kênh tách rời" type="button">≋</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="pitchfork" title="Pitchfork cổ điển" type="button">Ψ</button>
                            <button class="drawing-tool" data-chart-tool="inside-pitchfork" title="Pitchfork bên trong" type="button">I</button>
                            <button class="drawing-tool" data-chart-tool="schiff-pitchfork" title="Pitchfork Schiff" type="button">S</button>
                            <button class="drawing-tool" data-chart-tool="modified-schiff" title="Pitchfork Schiff điều chỉnh" type="button">M</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="rect" title="Vẽ vùng canh buy/canh sell" type="button">▭</button>
                            <button class="drawing-tool" data-chart-tool="fib" title="Vẽ Fibonacci hồi quy" type="button">F</button>
                            <button class="drawing-tool" data-chart-tool="fib-extension" title="Fibonacci mở rộng" type="button">FE</button>
                            <button class="drawing-tool" data-chart-tool="fib-channel" title="Kênh Fibonacci" type="button">FC</button>
                            <button class="drawing-tool" data-chart-tool="fib-time-zone" title="Mốc thời gian Fibonacci" type="button">FT</button>
                            <button class="drawing-tool" data-chart-tool="fib-fan" title="Quạt Fibonacci" type="button">FF</button>
                            <button class="drawing-tool" data-chart-tool="fib-circle" title="Vòng tròn/cung Fibonacci" type="button">○</button>
                            <button class="drawing-tool" data-chart-tool="fib-wedge" title="Nêm Fibonacci" type="button">▽</button>
                            <button class="drawing-tool" data-chart-tool="gann-fan" title="Quạt Gann" type="button">GF</button>
                            <button class="drawing-tool" data-chart-tool="gann-square" title="Hình vuông Gann" type="button">GS</button>
                            <button class="drawing-tool" data-chart-tool="gann-box" title="Hộp Gann" type="button">GB</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="xabcd" title="Mô hình XABCD" type="button">X</button>
                            <button class="drawing-tool" data-chart-tool="abcd" title="Mô hình ABCD" type="button">AB</button>
                            <button class="drawing-tool" data-chart-tool="triangle-pattern" title="Mô hình tam giác" type="button">△</button>
                            <button class="drawing-tool" data-chart-tool="three-drives" title="Mô hình ba nhịp" type="button">3D</button>
                            <button class="drawing-tool" data-chart-tool="head-shoulders" title="Mô hình vai đầu vai" type="button">HS</button>
                            <button class="drawing-tool" data-chart-tool="elliott-wave" title="Sóng Elliott" type="button">EW</button>
                            <button class="drawing-tool" data-chart-tool="cyclic-lines" title="Các đường chu kỳ" type="button">CL</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="long-position" data-tool-favorite="1" title="Tạo vị thế mua" type="button">L</button>
                            <button class="drawing-tool" data-chart-tool="short-position" data-tool-favorite="1" title="Tạo vị thế bán" type="button">S</button>
                            <button class="drawing-tool" data-chart-tool="forecast" title="Dự báo" type="button">↝</button>
                            <button class="drawing-tool" data-chart-tool="bars-pattern" title="Mô hình thanh giá" type="button">BP</button>
                            <button class="drawing-tool" data-chart-tool="anchored-vwap" title="VWAP có điểm neo" type="button">AV</button>
                            <button class="drawing-tool" data-chart-tool="volume-profile" title="Hồ sơ khối lượng" type="button">VP</button>
                            <button class="drawing-tool" data-chart-tool="price-range" title="Khoảng giá" type="button">PR</button>
                            <button class="drawing-tool" data-chart-tool="date-range" title="Khoảng ngày" type="button">DR</button>
                            <button class="drawing-tool" data-chart-tool="date-price-range" title="Khoảng ngày và giá" type="button">DPR</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="rotated-rect" title="Hình chữ nhật xoay" type="button">▱</button>
                            <button class="drawing-tool" data-chart-tool="circle" title="Hình tròn" type="button">○</button>
                            <button class="drawing-tool" data-chart-tool="ellipse" title="Hình elip" type="button">◯</button>
                            <button class="drawing-tool" data-chart-tool="triangle-shape" title="Hình tam giác" type="button">△</button>
                            <button class="drawing-tool" data-chart-tool="polyline" title="Đường gấp khúc" type="button">PL</button>
                            <button class="drawing-tool" data-chart-tool="path" title="Đường tự do" type="button">P</button>
                            <button class="drawing-tool" data-chart-tool="arc" title="Cung tròn" type="button">⌒</button>
                            <button class="drawing-tool" data-chart-tool="curve" title="Đường cong" type="button">~</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="brush" title="Bút vẽ" type="button">✎</button>
                            <button class="drawing-tool" data-chart-tool="highlighter" title="Bút đánh dấu" type="button">▰</button>
                            <button class="drawing-tool" data-chart-tool="arrow" title="Mũi tên" type="button">→</button>
                            <button class="drawing-tool" data-chart-tool="arrow-marker" title="Dấu mũi tên" type="button">➤</button>
                            <button class="drawing-tool" data-chart-tool="up-arrow" title="Mũi tên lên" type="button">↑</button>
                            <button class="drawing-tool" data-chart-tool="down-arrow" title="Mũi tên xuống" type="button">↓</button>
                            <span class="drawing-separator"></span>
                            <button class="drawing-tool" data-chart-tool="measure" title="Đo số nến, pip và phần trăm" type="button">↕</button>
                            <button class="drawing-tool" data-chart-tool="text" title="Thêm ghi chú trên biểu đồ" type="button">T</button>
                            <button class="drawing-tool" data-chart-tool="note" title="Ghi chú" type="button">N</button>
                            <button class="drawing-tool" data-chart-tool="price-note" title="Ghi chú giá" type="button">PN</button>
                            <button class="drawing-tool" data-chart-tool="callout" title="Chú thích nổi" type="button">C</button>
                            <button class="drawing-tool" data-chart-tool="comment" title="Bình luận" type="button">CM</button>
                            <button class="drawing-tool" data-chart-tool="price-label" title="Nhãn giá" type="button">PL</button>
                            <button class="drawing-tool" data-chart-tool="table" title="Bảng" type="button">Tb</button>
                            <button class="drawing-tool" data-chart-tool="pin" title="Ghim" type="button">⌖</button>
                            <button class="drawing-tool" data-chart-tool="flag" title="Cờ đánh dấu" type="button">⚑</button>
                            <button class="drawing-tool" data-chart-tool="icon" title="Biểu tượng/nhãn dán" type="button">★</button>
                            <button class="drawing-tool" data-chart-tool="alert" title="Đặt cảnh báo giá thủ công" type="button">!</button>
                            <button class="drawing-tool" id="chartZoomBoxBtn" title="Phóng to vùng chọn" type="button">Phóng to</button>
                            <button class="drawing-tool" id="chartDeleteSelectedBtn" title="Xóa hình vẽ đã chọn" type="button">Xóa</button>
                            <button class="drawing-tool" id="chartClearDrawingsBtn" title="Xóa toàn bộ hình vẽ" type="button">×</button>
                        </div>
                        <div class="chart-canvas-wrap">
                            <canvas id="marketChart" width="1200" height="560"></canvas>
                            <div class="chart-tooltip" id="chartTooltip"></div>
                            <div class="favorite-toolbar" id="chartFavoriteToolbar"></div>
                            <div class="pa-replay-floating-panel" id="chartReplayFloatingPanel" hidden>
                                <div class="pa-replay-floating-head" id="chartReplayDragHandle">
                                    <span class="pa-replay-grip" aria-hidden="true">⋮⋮</span>
                                    <strong>Tua nến backtest</strong>
                                    <small>Kéo để đặt vị trí</small>
                                    <button class="chart-control" id="chartReplayPanelCloseBtn" type="button" title="Thu gọn thanh tua nến">×</button>
                                </div>
                                <div class="pa-replay-floating-body" id="chartReplayFloatingBody"></div>
                            </div>
                            <div class="floating-style-toolbar" id="chartFloatingToolbar" hidden>
                                <span class="floating-toolbar-grip" id="drawingToolbarDragHandle" title="Kéo thanh công cụ" aria-label="Kéo thanh công cụ">⋮⋮</span>
                                <input id="drawingColorInput" type="color" value="#1f6feb" title="Màu">
                                <select id="drawingWidthSelect" title="Độ dày">
                                    <option value="1">1px</option>
                                    <option value="2" selected>2px</option>
                                    <option value="3">3px</option>
                                    <option value="4">4px</option>
                                </select>
                                <select id="drawingLineStyleSelect" title="Kiểu đường">
                                    <option value="solid">Liền</option>
                                    <option value="dashed">Nét đứt</option>
                                    <option value="dotted">Chấm</option>
                                </select>
                                <input id="drawingOpacityInput" type="range" min="0" max="100" value="16" title="Độ mờ màu nền">
                                <select id="drawingTextSizeSelect" title="Cỡ chữ">
                                    <option value="11">11</option>
                                    <option value="12" selected>12</option>
                                    <option value="14">14</option>
                                    <option value="16">16</option>
                                    <option value="20">20</option>
                                </select>
                                <button class="chart-control" id="drawingLockBtn" type="button">Khóa</button>
                                <button class="chart-control" id="drawingHideBtn" type="button">Ẩn</button>
                                <button class="chart-control" id="drawingSavePaZoneBtn" type="button" title="Thêm vùng AI chưa bắt được vào Dạy PA">+ Vùng</button>
                                <button class="chart-control" id="drawingJumpH4Btn" type="button" hidden>H4</button>
                                <button class="chart-control" id="drawingJumpH1Btn" type="button" hidden>H1</button>
                                <button class="chart-control" id="drawingJumpM15Btn" type="button" hidden>M15</button>
                                <button class="chart-control" id="drawingCloneBtn" type="button">Nhân bản</button>
                                <button class="chart-control" id="drawingDeleteBtn" type="button">Xóa</button>
                                <button class="chart-control" id="drawingPropertiesBtn" type="button">Thuộc tính</button>
                                <button class="chart-control" id="drawingSaveStyleBtn" type="button">Lưu kiểu</button>
                            </div>
                            <div class="chart-quick-menu" id="chartQuickMenu" hidden>
                                <button type="button" class="chart-quick-menu-priority" data-chart-menu-action="fit-full">⤢ Căn vừa biểu đồ</button>
                                <button type="button" class="chart-quick-menu-priority" data-chart-menu-action="fit-case">⌖ Zoom vùng ca đang chọn</button>
                                <button type="button" class="chart-quick-menu-priority" data-chart-menu-action="fit-horizontal">↔ Căn ngang</button>
                                <button type="button" class="chart-quick-menu-priority is-section-end" data-chart-menu-action="fit-vertical">↕ Căn dọc</button>
                                <button type="button" class="chart-quick-menu-priority is-section-end" data-chart-menu-action="toggle-candle-info">Hiện thông tin nến</button>
                                <button type="button" data-chart-menu-action="cursor">Con trỏ / kéo biểu đồ</button>
                                <button type="button" data-chart-menu-action="select">Quét chọn</button>
                                <button type="button" data-chart-menu-action="copy-selected">Sao chép hình vẽ</button>
                                <button type="button" data-chart-menu-action="paste-drawing">Dán hình vẽ</button>
                                <button type="button" data-chart-menu-action="duplicate-selected">Nhân đôi hình vẽ</button>
                                <button type="button" data-chart-menu-action="edit-text">Đổi ghi chú</button>
                                <button type="button" data-chart-menu-action="toggle-lock">Khóa / mở khóa</button>
                                <button type="button" data-chart-menu-action="toggle-hidden">Ẩn / hiện</button>
                                <button type="button" data-chart-menu-action="bring-front">Đưa lên trước</button>
                                <button type="button" data-chart-menu-action="send-back">Đưa ra sau</button>
                                <button type="button" data-chart-menu-action="add-alert">Thêm cảnh báo</button>
                                <button type="button" data-chart-menu-action="add-favorite">Thêm công cụ yêu thích</button>
                                <button type="button" data-chart-menu-action="delete-selected">Xóa đã chọn</button>
                                <button type="button" data-chart-menu-action="erase-nearest">Xóa gần chuột</button>
                                <button type="button" data-chart-menu-action="remove-selected">Xóa mục đã chọn</button>
                                <button type="button" data-chart-menu-action="remove-all">Xóa toàn bộ hình vẽ</button>
                            </div>
                        </div>
                        <aside class="chart-watchlist">
                            <div class="watchlist-head">
                                <strong>Danh sách cặp</strong>
                                <span id="watchlistCount">0</span>
                            </div>
                            <div id="symbolWatchlist" class="symbol-watchlist"></div>
                            <div class="drawing-list-head backtest-side-head">
                                <strong>Ca trade</strong>
                                <span id="chartSideTradeCount">0</span>
                            </div>
                            <div id="chartSideBacktestStats" class="chart-side-list backtest-side-stats"></div>
                            <div id="chartSideDiagnostics" class="chart-side-list backtest-side-diagnostics"></div>
                            <div id="chartSideTradeList" class="chart-side-list chart-side-trades"></div>
                            <div class="drawing-list-head">
                                <strong>Hình vẽ</strong>
                                <span id="drawingListCount">0</span>
                            </div>
                            <div id="chartDrawingList" class="chart-drawing-list"></div>
                            <div class="drawing-list-head">
                                <strong>Cảnh báo</strong>
                                <span id="chartAlertCount">0</span>
                            </div>
                            <div id="chartAlertList" class="chart-side-list"></div>
                            <div class="drawing-list-head">
                                <strong>Cửa sổ dữ liệu</strong>
                            </div>
                            <div id="chartDataWindow" class="chart-side-list"></div>
                            <div class="drawing-list-head">
                                <strong>Tin tức</strong>
                            </div>
                            <div id="chartNewsList" class="chart-side-list"></div>
                        </aside>
                    </div>
                    <div class="chart-bottom-bar">
                        <button class="chart-control" data-range-preset="1D" type="button">1D</button>
                        <button class="chart-control" data-range-preset="5D" type="button">5D</button>
                        <button class="chart-control" data-range-preset="1M" type="button">1M</button>
                        <button class="chart-control" data-range-preset="3M" type="button">3M</button>
                        <button class="chart-control" data-range-preset="6M" type="button">6M</button>
                        <button class="chart-control" data-range-preset="YTD" type="button">YTD</button>
                        <button class="chart-control" data-range-preset="1Y" type="button">1Y</button>
                        <button class="chart-control" data-range-preset="5Y" type="button">5Y</button>
                        <button class="chart-control" data-range-preset="ALL" type="button">Tất cả</button>
                        <input id="chartGoToDate" type="date" aria-label="Đi tới ngày">
                        <button class="chart-control" id="chartGoToDateBtn" type="button">Đi</button>
                        <select id="chartTimezoneSelect" aria-label="Múi giờ">
                            <option value="UTC">UTC</option>
                            <option value="local">Giờ máy</option>
                            <option value="Asia/Bangkok">Asia/Bangkok</option>
                            <option value="America/New_York">New York</option>
                            <option value="Europe/London">London</option>
                        </select>
                        <select id="chartScaleModeSelect" aria-label="Thang giá">
                            <option value="auto">Tự động</option>
                            <option value="regular">Thông thường</option>
                            <option value="log">Logarit</option>
                            <option value="percent">Phần trăm</option>
                        </select>
                        <button class="chart-control" id="chartTradingPanelBtn" type="button">Giao dịch thử</button>
                        <span id="chartBottomStatus">Sẵn sàng</span>
                    </div>
                    <section class="paper-trade-panel" id="chartTradingPanel" hidden>
                        <strong>Giao dịch thử</strong>
                        <select id="paperTradeSide">
                            <option value="long">Mua</option>
                            <option value="short">Bán</option>
                        </select>
                        <input id="paperTradeSize" type="number" min="0.01" step="0.01" value="1">
                        <button class="chart-control" id="paperTradePlaceBtn" type="button">Đặt lệnh giấy</button>
                        <div id="paperTradeLog"></div>
                    </section>
                    <div class="backtest-summary-grid" id="backtestSummary"></div>
                    <div class="backtest-detail-grid">
                        <section class="backtest-detail" id="tradeDetail"></section>
                        <section class="backtest-detail" id="dataWarningPanel"></section>
                        <section class="backtest-detail trade-list-panel">
                            <div class="backtest-detail-head">
                                <strong>Danh sách lệnh</strong>
                                <span id="tradeListCount">0</span>
                            </div>
                            <div class="backtest-trade-list" id="backtestTradeList"></div>
                        </section>
                    </div>
                </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-trainer">
                <div class="trainer-layout">
                    <section class="panel chart-panel">
                        <div class="panel-header">
                            <h2 id="caseTitle">Tình huống</h2>
                            <div class="case-nav">
                                <button class="icon-button" id="prevCaseBtn" type="button" aria-label="Tình huống trước">Trước</button>
                                <span id="casePosition">0 / 0</span>
                                <button class="icon-button" id="nextCaseBtn" type="button" aria-label="Tình huống sau">Sau</button>
                            </div>
                        </div>
                        <canvas id="caseChart" width="980" height="460"></canvas>
                    </section>

                    <aside class="panel decision-panel">
                        <div class="panel-header">
                            <h2>Quyết định</h2>
                            <button class="button secondary" id="scoreCaseBtn" type="button">Chấm điểm</button>
                        </div>
                        <div class="decision-summary" id="decisionSummary"></div>
                        <div class="case-fields" id="caseFields"></div>
                        <textarea id="reviewNote" rows="4" placeholder="Ghi chú của bạn"></textarea>
                        <div class="decision-actions">
                            <button class="button success" data-review="approved" type="button">Duyệt</button>
                            <button class="button warning" data-review="edited" type="button">Lưu sửa</button>
                            <button class="button danger" data-review="rejected" type="button">Bỏ qua</button>
                        </div>
                    </aside>
                </div>
            </section>


            <section class="tab-panel" id="tab-pa-training">
                <section class="panel pa-coach-shell pa-coach-compact pa-workspace-v2">
                    <div class="pa-coach-toolbar" id="paLegacyControls" hidden>
                        <div class="pa-coach-title">
                            <strong>Dạy PA</strong>
                            <span>Chart trái · AI phải</span>
                        </div>
                        <label class="field pa-compact-field">
                            <span>Cặp</span>
                            <div class="pa-symbol-search-wrap">
                                <input id="paTrainingSymbolSearch" type="search" list="paTrainingSymbolList" autocomplete="off" placeholder="Tìm cặp..." aria-label="Tìm cặp tiền">
                                <datalist id="paTrainingSymbolList"></datalist>
                                <select id="paTrainingSymbolSelect" aria-label="Cặp tiền đang chọn" hidden></select>
                            </div>
                        </label>
                        <label class="field pa-compact-field is-timeframe">
                            <span>Khung chính</span>
                            <select id="paTrainingTimeframeSelect">
                                <option value="H4">H4 / M15</option>
                            </select>
                        </label>
                        <label class="field pa-compact-field">
                            <span>Lọc vùng</span>
                            <select id="paTrainingZoneFilter">
                                <option value="active">Vùng còn dùng được</option>
                                <option value="fresh">Chưa chạm lại</option>
                                <option value="touched">Đã chạm lại</option>
                                <option value="weak">Vùng yếu</option>
                                <option value="broken">Đã bị phá</option>
                                <option value="manual">Vùng tự thêm</option>
                                <option value="all">Tất cả</option>
                            </select>
                        </label>
                        <div class="pa-compact-actions">
                            <button class="button secondary pa-icon-button" id="paTrainingRefreshBtn" title="Tải lại bộ nhớ" aria-label="Tải lại bộ nhớ" type="button">↻</button>
                            <label class="pa-case-count-control" title="Số ca cần tìm"><span>Ca</span><select id="paTrainingCaseCount" aria-label="Số ca cần tạo"><option value="1" selected>1</option><option value="5">5</option><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label>
                            <button class="button" id="paTrainingLoadZonesBtn" type="button">Tạo ca</button>
                            <button class="button secondary" id="paTrainingStartCasesBtn" type="button">Bắt đầu</button>
                            <div class="segmented-control pa-compact-segments" role="tablist" aria-label="Bước dạy Price Action">
                                <button class="segment-button is-active" id="paTrainingZonesBtn" type="button">Ca trade</button>
                            </div>
                        </div>
                        <div class="notice pa-status-line is-compact" id="paTrainingStatus">Chọn số ca cần tìm rồi bấm Tạo ca; mặc định tìm 1 ca trade.</div>
                    </div>

                    <div class="pa-coach-workspace">
                        <section class="pa-chart-workbench">
                            <header class="pa-workbench-header">
                                <h2>Dạy PA</h2>
                                <div class="pa-market-heading">
                                    <span class="pa-market-dot" aria-hidden="true"></span>
                                    <button class="pa-market-symbol-button" id="paWorkspaceSymbolButton" type="button" aria-haspopup="listbox" aria-expanded="false" aria-controls="paSymbolPopover">
                                        <strong id="paWorkspaceSymbol">—</strong>
                                    </button>
                                    <span class="pa-market-timeframe-switch" id="paWorkspaceTimeframe" aria-label="Đổi nhanh khung biểu đồ">
                                        <button class="pa-market-timeframe-button" id="paWorkspaceTfH4" type="button" data-pa-header-timeframe="H4">H4</button>
                                        <span aria-hidden="true">·</span>
                                        <button class="pa-market-timeframe-button" id="paWorkspaceTfM15" type="button" data-pa-header-timeframe="M15">M15</button>
                                    </span>
                                </div>
                                <strong class="pa-market-price" id="paWorkspacePrice">—</strong>
                            </header>
                            <div class="pa-toolbar-host">
                                <div class="pa-icon-toolbar" id="paIconToolbar" role="toolbar" aria-label="Công cụ biểu đồ Dạy PA"></div>
                                <section class="pa-tool-popover" id="paSymbolPopover" aria-label="Chọn cặp tiền" hidden></section>
                                <section class="pa-tool-popover" id="paTimeframePopover" aria-label="Chọn khung thời gian" hidden></section>
                                <section class="pa-tool-popover" id="paFilterPopover" aria-label="Lọc vùng giá" hidden></section>
                                <section class="pa-tool-popover" id="paSettingsPopover" aria-label="Cài đặt biểu đồ" hidden></section>
                                <section class="pa-tool-popover" id="paMorePopover" aria-label="Thêm công cụ" hidden></section>
                            </div>
                            <div id="paTrainingChartMount" class="shared-market-chart-mount pa-training-chart-mount">
                                <div class="notice">Chart TradingView từ mục Dữ liệu & MT5 sẽ hiện ở đây. Bấm Tải vùng H4 hoặc chọn cặp tiền để bắt đầu vẽ/chấm vùng.</div>
                            </div>

                        </section>

                        <aside class="pa-ai-side" id="paTrainingAiChatPanel">
                            <div class="pa-ai-side-head">
                                <span class="pa-coach-avatar" data-pa-icon="sparkles" aria-hidden="true"></span>
                                <div>
                                    <h2>AI Coach</h2>
                                    <span>Trợ lý phân tích giao dịch</span>
                                </div>
                                <button class="pa-tool-button" id="paCoachMoreBtn" type="button" aria-label="Thêm hành động AI Coach" title="Thêm hành động AI Coach" aria-expanded="false" aria-controls="paCoachMoreActions" data-pa-icon="more"></button>
                            </div>
                            <div class="pa-coach-context"><span class="pa-market-dot" aria-hidden="true"></span><div><strong id="paCoachContextSymbol">—</strong><span id="paCoachContextLabel">Chart đang xem</span></div></div>
                            <div class="pa-ai-zone-summary" id="paTrainingCoachZoneSummary"></div>
                            <div class="pa-ai-lessons" id="paTrainingRelevantLessons"></div>
                            <div class="pa-ai-chat-log" id="paTrainingChatLog" aria-live="polite"></div>
                            <div class="pa-ai-block-title" id="paTrainingChatComposerLabel">Soạn tin cho AI</div>
                            <div class="pa-ai-chat-input" aria-labelledby="paTrainingChatComposerLabel">
                                <button class="pa-tool-button pa-chat-attach" id="paChatChartContextBtn" type="button" title="Khoanh vùng biểu đồ để hỏi AI" aria-label="Khoanh vùng biểu đồ để hỏi AI" data-pa-icon="paperclip"></button>
                                <textarea id="paTrainingChatInput" rows="2" aria-label="Hỏi AI về ca giao dịch" placeholder="Hỏi AI về ca giao dịch..."></textarea>
                                <button class="button" id="paTrainingChatSendBtn" type="button" title="Gửi AI" aria-label="Gửi AI" data-pa-icon="send"></button>
                            </div>
                            <div class="pa-ai-session-strip" id="paTrainingSessionStrip"></div>
                            <div class="pa-ai-action-row" aria-label="Hành động nhanh AI Coach">
                                <button class="button success pa-ai-quick-action" type="button" title="Đúng: chọn kết luận tạm" aria-label="Đúng: chọn kết luận tạm" aria-expanded="false" data-pa-action-title="Đúng: chọn kết luận tạm" data-pa-action-help="Dùng khi ca hiện tại đúng với cách m muốn AI học: vùng H4 ổn, setup vào lệnh hợp lý, SL/TP chấp nhận được. Nút này chỉ chọn kết luận tạm; muốn lưu cả chat, biểu đồ và lệnh thì bấm Lưu phiên." data-pa-action-items="Nên chat/ghi lý do trước, rồi chọn Đúng.|Chưa lưu vào bộ nhớ cho tới khi bấm Lưu phiên." data-pa-case-decision="good_case" data-pa-case-label="Ca đúng cách trade"><span aria-hidden="true">✓</span><span>Đúng</span></button>
                                <button class="button danger pa-ai-quick-action" type="button" title="Bỏ: chọn kết luận tạm" aria-label="Bỏ: chọn kết luận tạm" aria-expanded="false" data-pa-action-title="Bỏ: chọn kết luận tạm" data-pa-action-help="Dùng khi ca này không nên trade: vùng xấu, entry sai, SL/TP vô lý hoặc không đúng cách nhìn của m. Nút này chỉ chọn kết luận tạm; bấm Lưu phiên mới lưu cả hồ sơ ca." data-pa-action-items="Nên nói rõ vì sao bỏ: vùng đã bị phá, phản ứng yếu, vào sớm, ngược trend...|AI sẽ học mẫu cần tránh sau khi m bấm Lưu phiên." data-pa-case-decision="bad_case" data-pa-case-label="Bỏ ca này"><span aria-hidden="true">×</span><span>Bỏ</span></button>
                                <button class="button secondary pa-ai-quick-action" type="button" title="Sửa: chọn kết luận tạm" aria-label="Sửa: chọn kết luận tạm" aria-expanded="false" data-pa-action-title="Sửa: chọn kết luận tạm" data-pa-action-help="Dùng khi ca này có ý tưởng nhưng còn lỗi: vùng chưa đẹp, entry sớm, SL/TP cần đổi hoặc cần điều kiện xác nhận thêm. Nút này chỉ đánh dấu tạm, Lưu phiên mới gom cả chat và chart vào bộ nhớ." data-pa-action-items="Nên ghi rõ cần sửa gì trong ô chat trước khi lưu.|Ví dụ: entry phải đợi phá cấu trúc M15, SL phải dưới đáy gần nhất." data-pa-case-decision="weak_case" data-pa-case-label="Ca yếu/cần sửa"><span aria-hidden="true">!</span><span>Sửa</span></button>
                                <button class="button pa-ai-quick-action pa-ai-save-session" type="button" title="Lưu phiên: gom toàn bộ ca" aria-label="Lưu phiên: gom toàn bộ ca" aria-expanded="false" data-pa-action-title="Lưu phiên: gom toàn bộ ca" data-pa-action-help="Bấm khi đã bàn xong ca này. App sẽ lưu kết luận tạm, toàn bộ chat của phiên, biểu đồ đang xem, hình vẽ, chỉ báo, vùng H4 và thông tin entry/SL/TP vào bộ nhớ AI." data-pa-action-items="Nên chọn Đúng/Bỏ/Sửa trước khi lưu.|Sau khi lưu, app chuyển sang ca tiếp theo để m dạy tiếp." data-pa-session-save="current"><span aria-hidden="true">▣</span><span>Lưu phiên</span></button>
                                <button class="button pa-ai-quick-action" type="button" title="Tiếp: sang ca kế tiếp" aria-label="Tiếp: sang ca kế tiếp" aria-expanded="false" data-pa-action-title="Tiếp: sang ca kế tiếp" data-pa-action-help="Dùng khi m chỉ muốn xem ca khác trước, chưa muốn lưu ca hiện tại. Nút này gửi lệnh chuyển sang ca trade tiếp theo và không lưu quyết định vào bộ nhớ." data-pa-action-items="Hợp khi ca hiện tại chưa rõ hoặc m muốn lướt nhanh hàng đợi.|Muốn AI học thì chọn Đúng/Bỏ/Sửa rồi bấm Lưu phiên." data-pa-chat-prompt="Sang ca trade tiếp theo"><span aria-hidden="true">→</span><span>Tiếp</span></button>
                                <button class="button secondary pa-ai-quick-action" type="button" title="PT: nhờ AI phân tích ca" aria-label="PT: nhờ AI phân tích ca" aria-expanded="false" data-pa-action-title="PT: nhờ AI phân tích ca" data-pa-action-help="Dùng khi m muốn AI phân tích ca đang xem: vùng H4, phản ứng giá, setup Price Action, entry, SL và TP. Nút này chỉ gửi câu hỏi cho AI, không tự lưu kết quả." data-pa-action-items="Sau khi AI trả lời, m có thể chat thêm rồi Lưu phiên.|Nếu câu trả lời có rule chung, có thể bấm Học để lưu thành bài học riêng." data-pa-chat-prompt="Phân tích ca trade này giúp tôi: vùng H4, setup vào lệnh, SL và TP có hợp không?"><span aria-hidden="true">?</span><span>PT</span></button>
                                <button class="button secondary pa-ai-quick-action" type="button" title="Học: lưu đoạn chat thành bài học" aria-label="Học: lưu đoạn chat thành bài học" aria-expanded="false" data-pa-action-title="Học: lưu đoạn chat thành bài học" data-pa-action-help="Dùng sau khi đoạn chat có một rule hoặc kinh nghiệm hay. Bấm để lưu nội dung trao đổi thành bài học Price Action chung, lần sau AI sẽ dùng lại khi chat hoặc chấm ca." data-pa-action-items="Hợp để lưu các câu như: vùng chạm nhiều lần thì bỏ, entry phải có xác nhận M15.|Không thay thế Lưu phiên; nó chỉ lưu bài học chung từ hội thoại." data-pa-lesson-save="current"><span aria-hidden="true">★</span><span>Học</span></button>
                                <button class="button secondary pa-ai-quick-action" type="button" title="M15: soi setup vào lệnh" aria-label="M15: soi setup vào lệnh" aria-expanded="false" data-pa-action-title="M15: soi setup vào lệnh" data-pa-action-help="Dùng khi muốn xem setup vào lệnh ở M15 quanh vùng H4 đang xử. Nút này gửi yêu cầu mở/chuyển chart M15 đúng ca đang xem, không lưu quyết định vào bộ nhớ." data-pa-action-items="Hợp khi H4 có vùng rồi nhưng cần nhìn phản ứng nhỏ hơn để vào lệnh.|Các vùng/hình vẽ đã lưu sẽ đi cùng khi đổi khung." data-pa-chat-prompt="Mở chart M15 cho ca trade này"><span aria-hidden="true">15</span><span>M15</span></button>
                                <button class="button secondary pa-ai-quick-action" type="button" title="Bỏ qua ca này, sang ca kế tiếp" aria-label="Bỏ qua ca này, sang ca kế tiếp" aria-expanded="false" data-pa-action-title="Bỏ qua ca này" data-pa-action-help="Chuyển sang ca trade kế tiếp mà không đánh dấu ca hiện tại là đúng, sai hay cần sửa." data-pa-action-items="Không lưu kết luận hoặc thay đổi nào.|Ca này vẫn có thể xem lại trong danh sách." data-pa-case-skip><span aria-hidden="true">⏭</span><span>Bỏ qua</span></button>
                            </div>
                        </aside>
                    </div>
                </section>

                <div class="pa-training-view" id="paTrainingZonesView">
                    <section class="panel pa-training-list-panel pa-case-queue-panel">
                        <div class="panel-header">
                            <div>
                                <h2>Danh sách ca trade</h2>
                                <span id="paTrainingZoneCount">0 ca</span>
                            </div>
                            <span class="pa-case-queue-hint">Bấm một ca để xem lý do ở AI Coach</span>
                        </div>
                        <div class="pa-zone-list" id="paTrainingZoneList"></div>
                    </section>
                </div>

                <div class="pa-training-view" id="paTrainingSetupsView" hidden>
                    <section class="panel pa-training-setup-panel">
                        <div class="panel-header">
                            <div>
                                <h2>Chấm setup M15 quanh vùng đã duyệt</h2>
                                <span>Bước này dùng sau khi bạn đã có đủ vùng H4 được duyệt. App sẽ lọc setup quanh những vùng đó để bạn chấm entry, SL và TP.</span>
                            </div>
                        </div>
                        <div id="paTrainingSetupGuide"></div>
                    </section>
                </div>
            </section>            <section class="tab-panel" id="tab-memory">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Bộ nhớ huấn luyện</h2>
                            <div class="topbar-actions">
                                <button class="button secondary" id="runEvalBtn" type="button">Chạy đánh giá</button>
                                <a class="button" href="api.php?action=export_memory">Tải JSON</a>
                            </div>
                        </div>
                        <div class="memory-summary" id="memorySummary"></div>
                        <div class="notice" id="evalResult">Chưa chạy đánh giá.</div>
                    </section>
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Nhập bộ nhớ</h2>
                        </div>
                        <input id="memoryImport" type="file" accept="application/json">
                        <div class="notice">
                            Khi nhập, hệ thống sẽ thêm các tình huống đã rà soát và thay thế hồ sơ tài khoản, chiến lược, danh sách tình huống từ tệp bộ nhớ.
                        </div>
                    </section>
                </div>
            </section>

            <section class="tab-panel" id="tab-tradebrain-memory">
                <div class="workspace-grid two">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Bài học TradeBrain</h2>
                            <div class="topbar-actions">
                                <label class="field inline-field">
                                    <span>Loại bài học</span>
                                    <select id="tradebrainMemoryTypeFilter">
                                        <option value="">Tất cả loại bài học</option>
                                        <option value="strategy_lesson">Bài học chiến lược</option>
                                        <option value="backtest_lesson">Bài học kiểm thử</option>
                                        <option value="live_lesson">Bài học vận hành</option>
                                        <option value="risk_warning">Cảnh báo rủi ro</option>
                                        <option value="system_note">Ghi chú hệ thống</option>
                                    </select>
                                </label>
                                <label class="field inline-field">
                                    <span>Phiên bản liên quan</span>
                                    <select id="tradebrainMemoryVersionFilter"></select>
                                </label>
                            </div>
                        </div>
                        <div class="tradebrain-memory-list" id="tradebrainMemoryList"></div>
                    </section>
                    <section class="panel">
                        <div class="panel-header">
                            <h2>Nhật ký quyết định</h2>
                            <span id="tradebrainDecisionCount"></span>
                        </div>
                        <div class="decision-log-list" id="tradebrainDecisionList"></div>
                    </section>
                </div>
            </section>
        </main>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <div class="help-popover" id="helpPopover" role="tooltip" hidden></div>
    <div class="help-modal-backdrop" id="helpGuideModal" hidden>
        <section class="help-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="helpGuideTitle">
            <div class="help-modal-header">
                <h2 id="helpGuideTitle">Hướng dẫn vận hành AI Trade / TradeBrain</h2>
                <button class="icon-button" id="helpGuideCloseBtn" type="button" aria-label="Đóng hướng dẫn">Đóng</button>
            </div>
            <div class="help-modal-body" id="helpGuideBody"></div>
        </section>
    </div>
    <script src="assets/help-texts.js?v=20260618-vi4"></script>
    <script src="assets/pa-zones.js?v=20261011-cases"></script>
    <script src="assets/pa-case-manager.js?v=20261011-cases"></script>
    <script src="assets/pa-workspace.js?v=20261016-trade-level-markers"></script>
    <script src="assets/app.js?v=20261017-pa-memory-draw-fix"></script>
</body>
</html>









