(function () {
    let paWorkspaceUi = null;
    let paCaseManager = null;
    let paNearbyZoneCache = null;
    let paNearbyZonesVisible = false;
    let paWorkspacePersistTimer = null;
    let chartInteractionFrame = 0;
    let chartInteractionData = null;
    let chartCrosshairFrame = 0;
    const LEGACY_DEFAULT_MT5_BARS = 10000;
    const DEFAULT_MT5_BARS = 100000;
    const MAX_MT5_BARS = 100000;
    const MAX_MT5_FETCH_BARS = 1500000;
    const MT5_BASE_MINUTES = 15;
    const MT5_TIMEFRAME_MINUTES = {
        M1: 1,
        M5: 5,
        M15: 15,
        M30: 30,
        H1: 60,
        H4: 240,
        D1: 1440,
    };
    const MT5_SETTINGS_KEY = 'ai_trade_mt5_settings_v1';
    const CHART_DRAWINGS_KEY = 'ai_trade_chart_drawings_v1';
    const CHART_LAYOUTS_KEY = 'ai_trade_chart_layouts_v1';
    const CHART_SETTINGS_KEY = 'ai_trade_chart_settings_v1';
    const CHART_FAVORITES_KEY = 'ai_trade_chart_favorites_v1';
    const PA_CHAT_STORAGE_KEY = 'ai_trade_pa_chat_history_v1';
    const PA_WORKSPACE_STORAGE_KEY = 'ai_trade_pa_workspace_v1';
    const CHART_HISTORY_LIMIT = 60;
    const FIB_LEVELS = [0, 0.236, 0.382, 0.5, 0.618, 0.786, 1];
    const UI_TRANSLATIONS = {
        reasons: {
            ZONE_ALREADY_BROKEN: 'Vùng đã bị phá trước đó',
            WEAK_H4_ZONE: 'Vùng H4 quá yếu',
            NO_M15_CONFIRMATION: 'Chưa có xác nhận M15',
            DIRTY_SIDEWAY: 'Thị trường đi ngang nhiễu',
            BAD_RR: 'Tỷ lệ RR không đạt',
            LIMIT_NOT_FILLED: 'Lệnh chờ không được khớp',
            NO_H4_ZONE: 'Không có vùng H4 phù hợp',
            DATA_MISSING: 'Thiếu dữ liệu',
            NO_M15_REACTION: 'M15 chưa có phản ứng rõ ràng',
            NO_QML_FAKE_BREAK: 'Chưa có QML hoặc phá vỡ giả',
            NO_H4_CONTEXT: 'Thiếu bối cảnh H4',
            H4_ZONE_OK: 'Vùng H4 đạt yêu cầu',
            ACCEPT: 'Được chấp nhận',
            UNKNOWN: 'Chưa xác định',
        },
        zoneStates: {
            fresh: 'Vùng mới',
            touched: 'Đã chạm',
            weakened: 'Bị yếu đi',
            broken: 'Đã bị phá',
            active: 'Đang hiệu lực',
            strong: 'Mạnh',
            weak: 'Yếu',
            invalidated_by_touches: 'Mất hiệu lực do bị chạm nhiều lần',
        },
        presets: {
            strict: 'Nghiêm ngặt',
            balanced: 'Cân bằng',
            'loose-debug': 'Nới lỏng để kiểm tra',
            'balanced-calibrated-v1': 'Cân bằng đã hiệu chỉnh v1',
            current: 'Thiết lập hiện tại',
        },
        sampleStatuses: {
            INSUFFICIENT_SAMPLE: 'Chưa đủ mẫu',
            OK: 'Đủ mẫu',
            ERROR: 'Có lỗi',
        },
        decisions: {
            APPROVE: 'Chấp nhận',
            APPROVED: 'Đã chấp nhận',
            ACCEPT: 'Chấp nhận',
            REJECT: 'Từ chối',
            REJECTED: 'Đã từ chối',
            WAIT: 'Chờ thêm',
            MODIFY: 'Cần chỉnh lại',
            PASS: 'Đạt',
            FAIL: 'Không đạt',
            ENTER: 'Vào lệnh',
            SKIP: 'Bỏ qua',
        },
        reviewLabels: {
            ZONE_GOOD: 'Vùng tốt',
            ZONE_BAD: 'Vùng chưa tốt',
            ZONE_TRADEABLE: 'Vùng có thể giao dịch',
            ZONE_NOT_TRADEABLE: 'Vùng không nên giao dịch',
            BROKEN_CORRECT: 'Đánh dấu bị phá là đúng',
            BROKEN_WRONG: 'Đánh dấu bị phá là sai',
            STRENGTH_CORRECT: 'Chấm độ mạnh đúng',
            STRENGTH_TOO_LOW: 'Chấm độ mạnh quá thấp',
            STRENGTH_TOO_HIGH: 'Chấm độ mạnh quá cao',
            ORIGIN_CORRECT: 'Xác định điểm gốc đúng',
            ORIGIN_WRONG: 'Xác định điểm gốc sai',
            REJECT_CORRECT: 'Từ chối đúng',
            REJECT_WRONG: 'Từ chối sai',
            SHOULD_HAVE_BEEN_TRADE: 'Lẽ ra nên vào lệnh',
            NO_M15_CONFIRM_CORRECT: 'Đánh giá thiếu xác nhận M15 là đúng',
            NO_M15_CONFIRM_WRONG: 'Đánh giá thiếu xác nhận M15 là sai',
            BAD_RR_CORRECT: 'Đánh giá RR không đạt là đúng',
            BAD_RR_WRONG: 'Đánh giá RR không đạt là sai',
            DIRTY_SIDEWAY_CORRECT: 'Đánh giá đi ngang nhiễu là đúng',
            DIRTY_SIDEWAY_WRONG: 'Đánh giá đi ngang nhiễu là sai',
            LIMIT_TOO_DEEP: 'Điểm đặt lệnh chờ quá sâu',
            LIMIT_REASONABLE: 'Điểm đặt lệnh chờ hợp lý',
            ENTRY_WRONG: 'Điểm vào sai',
            SL_WRONG: 'Stop Loss sai',
            TP_WRONG: 'Take Profit sai',
            ENTRY_GOOD: 'Điểm vào tốt',
            ENTRY_BAD: 'Điểm vào chưa tốt',
            SL_GOOD: 'Stop Loss hợp lý',
            SL_BAD: 'Stop Loss chưa hợp lý',
            TP_GOOD: 'Take Profit hợp lý',
            TP_BAD: 'Take Profit chưa hợp lý',
            SHOULD_NOT_TRADE: 'Không nên vào lệnh',
            GOOD_LOSS: 'Lệnh thua nhưng đúng kế hoạch',
            BAD_LOSS: 'Lệnh thua không tốt',
            GOOD_WIN: 'Lệnh thắng tốt',
            LUCKY_WIN: 'Lệnh thắng do may mắn',
        },
        statuses: {
            draft: 'Bản nháp',
            backtested: 'Đã kiểm thử',
            approved: 'Đã phê duyệt',
            rejected: 'Đã từ chối',
            retired: 'Đã ngừng dùng',
            live: 'Đang vận hành',
            validation_passed: 'Đã đạt kiểm tra xác thực',
            final_test_passed: 'Đã đạt kiểm tra cuối',
            accepted: 'Đã được chấp nhận',
            pending: 'Đang chờ',
            pending_user_approval: 'Chờ người dùng phê duyệt',
            testing: 'Đang kiểm thử',
            ok: 'Tốt',
            warning: 'Có cảnh báo',
            error: 'Có lỗi',
            user: 'Người dùng',
            ai: 'AI',
            calibration_advisor: 'Bộ gợi ý hiệu chỉnh',
            root: 'Bản gốc',
            running: 'Đang chạy',
            paused: 'Tạm dừng',
            completed: 'Hoàn thành',
            failed: 'Thất bại',
            archived: 'Lưu trữ',
            cancelled: 'Đã hủy',
            passed: 'Đạt',
            BacktestResult: 'Kết quả kiểm thử',
        },
        dataSplits: {
            train: 'Dữ liệu huấn luyện',
            validation: 'Dữ liệu xác thực',
            final: 'Dữ liệu kiểm tra cuối',
            custom: 'Khoảng tùy chọn',
        },
        reviewTypes: {
            zone: 'Vùng giá',
            candidate: 'Mẫu ứng viên',
            trade: 'Lệnh giao dịch',
        },
        queueTypes: {
            TRADES_ENTERED: 'Các lệnh đã vào',
            ZONES_LIFECYCLE: 'Vòng đời vùng giá',
        },
        memoryTypes: {
            strategy_lesson: 'Bài học chiến lược',
            backtest_lesson: 'Bài học kiểm thử',
            live_lesson: 'Bài học vận hành',
            risk_warning: 'Cảnh báo rủi ro',
            system_note: 'Ghi chú hệ thống',
        },
        decisionTypes: {
            strategy_upgrade: 'Nâng cấp chiến lược',
            backtest_review: 'Rà soát kết quả kiểm thử',
        },
        strengthFields: {
            bosScore: 'Điểm phá cấu trúc BOS',
            impulseScore: 'Điểm lực đẩy',
            freshnessScore: 'Điểm độ mới',
            touchPenalty: 'Điểm trừ do bị chạm',
            brokenPenalty: 'Điểm trừ do bị phá',
            trendContextScore: 'Điểm bối cảnh xu hướng',
            finalScore: 'Tổng điểm',
        },
        advisorAreas: {
            zoneLifecycle: 'Vòng đời vùng giá',
            zoneStrength: 'Độ mạnh vùng giá',
            m15Confirmation: 'Xác nhận M15',
            sidewayFilter: 'Bộ lọc đi ngang',
            rrFilter: 'Bộ lọc RR',
            entry: 'Điểm vào lệnh',
            stopLoss: 'Stop Loss',
            takeProfit: 'Take Profit',
        },
        ruleFields: {
            'h4Rules.zoneLifecycle.breakBufferAtr': 'Biên xác nhận phá vùng H4 theo ATR',
            'h4Rules.minZoneStrength': 'Điểm mạnh tối thiểu của vùng H4',
            'm15Rules.requireStructureBreak': 'Yêu cầu M15 phá cấu trúc',
            'm15Rules.avoidDirtySideway': 'Tránh thị trường đi ngang nhiễu',
            'entryRules.minDistanceToTPR': 'RR tối thiểu tới vùng TP',
            'entryRules.entryAt': 'Vị trí đặt lệnh chờ',
            'entryRules.stopBufferPoints': 'Khoảng đệm Stop Loss',
            'entryRules.takeProfitMode': 'Cách đặt Take Profit',
            'filters.requireH4Trend': 'Yêu cầu thuận xu hướng H4',
            zoneStrengthMin: 'Điểm mạnh tối thiểu của vùng',
            'filters.avoidSideway': 'Tránh thị trường đi ngang',
            rrMin: 'RR tối thiểu',
            rrMax: 'RR tối đa',
            'filters.requireStrongH4Zone': 'Yêu cầu vùng H4 mạnh',
            'h4Rules.detectTrend': 'Bật nhận diện xu hướng H4',
            minRR: 'RR tối thiểu',
        },
        ruleValues: {
            zone_extreme: 'Biên ngoài của vùng',
            zone_extreme_or_50_percent: 'Biên ngoài hoặc 50% vùng',
            zone_50_percent: 'Mức 50% của vùng',
            zone_open: 'Giá mở cửa của vùng',
            rr_or_opposite_zone: 'Theo RR hoặc vùng đối diện',
            opposite_h4_zone_or_nearest_liquidity: 'Vùng H4 đối diện hoặc thanh khoản gần nhất',
        },
        tags: {
            DEMAND_LIMIT: 'Lệnh chờ tại vùng canh buy',
            SUPPLY_LIMIT: 'Lệnh chờ tại vùng canh sell',
            H4_DEMAND: 'Vùng canh buy H4',
            H4_DEMAND_ZONE: 'Vùng canh buy H4',
            H4_SUPPLY: 'Vùng canh sell H4',
            H4_SUPPLY_ZONE: 'Vùng canh sell H4',
            H4_TREND_BEARISH: 'Xu hướng H4 giảm',
            H4_TREND_BULLISH: 'Xu hướng H4 tăng',
            H4_TREND_SIDEWAY: 'H4 đi ngang',
            H4_ZONE: 'Vùng H4',
            LIMIT_PENDING: 'Lệnh chờ đang chờ khớp',
            M15_BREAK_STRUCTURE: 'M15 phá cấu trúc',
            M15_REACTION: 'Phản ứng M15',
            QML_OR_FAKE_BREAK: 'QML hoặc phá vỡ giả',
            ZONE_STRENGTH_50_69: 'Độ mạnh vùng 50–69',
            ZONE_STRENGTH_70_84: 'Độ mạnh vùng 70–84',
            ZONE_STRENGTH_85_100: 'Độ mạnh vùng 85–100',
        },
    };
    const DISPLAY_TEXT_TRANSLATIONS = {
        'No clear M15 rejection/reaction candle at the H4 zone.': 'Chưa có nến từ chối hoặc phản ứng M15 rõ ràng tại vùng H4.',
        'No basic fake-break/QML pattern found at the zone.': 'Chưa tìm thấy mô hình phá vỡ giả hoặc QML cơ bản tại vùng.',
        'M15 candle is missing for setup confirmation.': 'Thiếu nến M15 để xác nhận mẫu vào lệnh.',
        'H4 zone high/low geometry is invalid.': 'Biên trên/dưới của vùng H4 không hợp lệ.',
        'Stop distance is invalid for this zone and entry.': 'Khoảng cách Stop Loss không hợp lệ với vùng và điểm vào này.',
        'Missing closed context timeframe trend.': 'Thiếu xu hướng từ khung thời gian bối cảnh đã đóng nến.',
        'Cloned from the current manual strategy.': 'Được sao chép từ chiến lược thủ công hiện tại.',
        'Created by TradeBrain Strategy Evolution Engine. Pending deterministic validation.': 'Được TradeBrain tạo từ quy trình tiến hóa chiến lược. Đang chờ kiểm tra xác thực.',
        'TradeBrain evaluator passed. Human approval is still required before paper/live.': 'Bộ đánh giá TradeBrain đã cho kết quả đạt. Vẫn cần người dùng phê duyệt trước khi chạy giao dịch thử hoặc vận hành thật.',
        'TradeBrain evaluator rejected this version after validation/final checks.': 'Bộ đánh giá TradeBrain đã từ chối phiên bản này sau khi kiểm tra xác thực hoặc kiểm tra cuối.',
        'TradeBrain lesson': 'Bài học TradeBrain',
        'not enough sample: validation totalTrades must be >= 50': 'Chưa đủ mẫu: dữ liệu xác thực cần có ít nhất 50 lệnh',
        'not enough sample: train result is marked INSUFFICIENT_SAMPLE': 'Chưa đủ mẫu: kết quả huấn luyện đang được đánh dấu chưa đủ mẫu',
        'not enough sample: validation result is marked INSUFFICIENT_SAMPLE': 'Chưa đủ mẫu: kết quả xác thực đang được đánh dấu chưa đủ mẫu',
        'Nhieu zone bi label BROKEN_WRONG; can yeu cau pha zone ro hon.': 'Nhiều vùng bị đánh dấu “bị phá là sai”; cần yêu cầu tín hiệu phá vùng rõ hơn.',
        'Nhieu zone bi label STRENGTH_TOO_LOW; detector dang cham diem qua khat khe.': 'Nhiều vùng bị chấm độ mạnh quá thấp; bộ phát hiện đang chấm điểm quá khắt khe.',
        'Nhieu zone bi label STRENGTH_TOO_HIGH; detector dang cho qua zone yeu.': 'Nhiều vùng bị chấm độ mạnh quá cao; bộ phát hiện đang cho qua vùng yếu.',
        'Nhieu candidate bi label NO_M15_CONFIRM_WRONG; nen noi dieu kien structure break hoac reaction.': 'Nhiều mẫu bị đánh dấu thiếu xác nhận M15 sai; nên nới điều kiện phá cấu trúc hoặc phản ứng giá.',
        'Nhieu candidate bi label DIRTY_SIDEWAY_WRONG; filter sideway co the dang chan qua nhieu.': 'Nhiều mẫu bị đánh dấu đi ngang nhiễu sai; bộ lọc đi ngang có thể đang chặn quá nhiều.',
        'Nhieu candidate bi label BAD_RR_WRONG; RR filter co the qua chat voi setup nay.': 'Nhiều mẫu bị đánh dấu RR không đạt sai; bộ lọc RR có thể quá chặt với mẫu này.',
        'Nhieu label LIMIT_TOO_DEEP; limit dat qua sau trong zone va kho fill.': 'Nhiều lệnh chờ bị đánh dấu đặt quá sâu trong vùng nên khó được khớp.',
        'Nhieu label ENTRY_WRONG/ENTRY_BAD; can test entry gan origin/open cua zone.': 'Nhiều điểm vào bị đánh giá chưa tốt; nên thử đặt gần điểm gốc hoặc giá mở cửa của vùng.',
        'Nhieu label SL_WRONG/SL_BAD; buffer stop co the qua mong.': 'Nhiều Stop Loss bị đánh giá chưa tốt; khoảng đệm Stop Loss có thể quá mỏng.',
        'Nhieu label TP_WRONG/TP_BAD; nen test TP theo opposite H4 zone hoac nearest liquidity.': 'Nhiều Take Profit bị đánh giá chưa tốt; nên thử đặt theo vùng H4 đối diện hoặc vùng thanh khoản gần nhất.',
        'Profit factor on train is weak, so the strategy needs stricter entry quality.': 'Hệ số lợi nhuận trên dữ liệu huấn luyện còn yếu, vì vậy chiến lược cần điều kiện vào lệnh chặt chẽ hơn.',
        'Losing groups often improve first by forcing alignment with the closed H4 trend.': 'Nhóm lệnh thua thường được cải thiện khi bắt buộc đi cùng xu hướng H4 đã đóng nến.',
        'Drawdown or loss streak is high relative to the current return.': 'Mức sụt giảm hoặc chuỗi lệnh thua đang cao so với lợi nhuận hiện tại.',
        'Raise the H4 zone-strength proxy so weaker context trades are filtered out.': 'Tăng ngưỡng độ mạnh vùng H4 để lọc bớt lệnh có bối cảnh yếu.',
        'Sideway conditions tend to create clustered losses in an EMA/structure proxy.': 'Điều kiện đi ngang thường tạo thành cụm lệnh thua trong cách đánh giá EMA/cấu trúc.',
        'Win rate is low, so validation should check whether a slightly lower RR produces a smoother curve.': 'Tỷ lệ thắng thấp; dữ liệu xác thực nên kiểm tra xem giảm nhẹ RR có giúp đường lợi nhuận ổn định hơn không.',
        'Lower RR a little to test whether exits become more realistic without destroying reward/risk.': 'Giảm nhẹ RR để kiểm tra điểm thoát thực tế hơn mà không làm hỏng tỷ lệ lợi nhuận/rủi ro.',
        'Keep rrMax aligned with rrMin for this v1 fixed-RR engine.': 'Giữ RR tối đa đồng bộ với RR tối thiểu trong bộ máy RR cố định v1.',
        'Many setups are rejected before entry, so the current filters may be too strict for available data.': 'Nhiều mẫu bị từ chối trước khi vào lệnh, nên các bộ lọc hiện tại có thể quá chặt với dữ liệu sẵn có.',
        'Train metrics do not show a clear reason to change rules aggressively.': 'Chỉ số trên dữ liệu huấn luyện chưa cho thấy lý do rõ ràng để thay đổi luật mạnh tay.',
        'Test a conservative version that only accepts stronger H4 context.': 'Thử một phiên bản thận trọng, chỉ chấp nhận bối cảnh H4 mạnh hơn.',
        'Validation metrics are included for comparison only; the proposed changes are based on train metrics.': 'Chỉ số xác thực chỉ dùng để so sánh; các thay đổi đề xuất dựa trên dữ liệu huấn luyện.',
        'Reduce noisy entries and drawdown before checking validation.': 'Giảm điểm vào nhiễu và mức sụt giảm trước khi kiểm tra trên dữ liệu xác thực.',
        'This is a mock reviewer. It can miss fast reversal trades and must be validated before approval.': 'Đây là bộ đánh giá mô phỏng. Hệ thống có thể bỏ lỡ lệnh đảo chiều nhanh và phải được xác thực trước khi phê duyệt.',
        'Profit factor train yếu, cần tăng chất lượng setup trước khi validation.': 'Hệ số lợi nhuận trên dữ liệu huấn luyện còn yếu; cần tăng chất lượng mẫu lệnh trước khi xác thực.',
        'Ép setup thuận trend H4 đóng nến để giảm lệnh nhiễu.': 'Yêu cầu mẫu lệnh thuận xu hướng H4 đã đóng nến để giảm lệnh nhiễu.',
        'Ưu tiên setup thuận trend H4': 'Ưu tiên mẫu lệnh thuận xu hướng H4',
        'Nhóm lệnh train có profit factor yếu, phiên bản kế tiếp nên kiểm tra filter H4 trend trên validation.': 'Nhóm lệnh huấn luyện có hệ số lợi nhuận yếu; phiên bản kế tiếp nên kiểm tra bộ lọc xu hướng H4 trên dữ liệu xác thực.',
        'Drawdown hoặc chuỗi thua cao; cần giảm setup sideway/yếu.': 'Mức sụt giảm hoặc chuỗi thua cao; cần giảm các mẫu đi ngang hoặc có bối cảnh yếu.',
        'Tăng ngưỡng strength để loại bớt vùng H4 yếu.': 'Tăng ngưỡng độ mạnh để loại bớt vùng H4 yếu.',
        'Sideway thường tạo cụm thua trong proxy EMA/structure.': 'Thị trường đi ngang thường tạo cụm lệnh thua trong cách đánh giá EMA/cấu trúc.',
        'Giảm lệnh trong vùng sideway': 'Giảm lệnh trong vùng đi ngang',
        'Khi drawdown hoặc loss streak tăng, nên kiểm tra filter sideway trước khi chấp nhận version mới.': 'Khi mức sụt giảm hoặc chuỗi thua tăng, nên kiểm tra bộ lọc đi ngang trước khi chấp nhận phiên bản mới.',
        'Winrate thấp; thử giảm nhẹ RR để kiểm tra tính thực tế của exit.': 'Tỷ lệ thắng thấp; thử giảm nhẹ RR để kiểm tra tính thực tế của điểm thoát.',
        'Giảm rất nhẹ RR, không tăng risk, sau đó bắt buộc validation/final.': 'Giảm rất nhẹ RR, không tăng rủi ro; sau đó bắt buộc kiểm tra trên dữ liệu xác thực và dữ liệu cuối.',
        'Keep target distance aligned with minRR.': 'Giữ khoảng cách mục tiêu đồng bộ với RR tối thiểu.',
        'Giữ rrMax đồng bộ với rrMin trong engine fixed-RR hiện tại.': 'Giữ RR tối đa đồng bộ với RR tối thiểu trong bộ máy RR cố định hiện tại.',
        'Có nhiều setup bị loại do spread, không nên nới spread trong bản AI tự nâng cấp.': 'Có nhiều mẫu bị loại do spread; không nên nới spread trong phiên bản AI tự nâng cấp.',
        'Không nới spread để tăng số lệnh': 'Không nới spread để tăng số lệnh',
        'Spread cao làm méo kết quả backtest; version AI không được tăng maxSpread chỉ để có thêm lệnh.': 'Spread cao làm sai lệch kết quả kiểm thử; phiên bản AI không được tăng spread tối đa chỉ để có thêm lệnh.',
        'Đã đọc memory lessons liên quan trước khi đề xuất thay đổi.': 'Đã đọc các bài học liên quan trong bộ nhớ trước khi đề xuất thay đổi.',
        'Train chưa cho thấy nguyên nhân rõ; chỉ thử một thay đổi bảo thủ.': 'Dữ liệu huấn luyện chưa cho thấy nguyên nhân rõ; chỉ thử một thay đổi thận trọng.',
        'Kiểm tra bản chỉ nhận context H4 mạnh hơn, không tăng risk.': 'Kiểm tra phiên bản chỉ nhận bối cảnh H4 mạnh hơn, không tăng rủi ro.',
        'Nếu train không chỉ ra vấn đề nổi bật, version mới chỉ nên thay đổi một filter bảo thủ rồi validation.': 'Nếu dữ liệu huấn luyện không chỉ ra vấn đề nổi bật, phiên bản mới chỉ nên thay đổi một bộ lọc thận trọng rồi xác thực.',
        'Giảm lệnh nhiễu và kiểm tra lại drawdown trên validation/final.': 'Giảm lệnh nhiễu và kiểm tra lại mức sụt giảm trên dữ liệu xác thực và dữ liệu cuối.',
        'Có thể giảm số lượng lệnh; không tăng riskPercent và không dùng kết quả train để tự approve.': 'Có thể giảm số lượng lệnh; không tăng tỷ lệ rủi ro và không dùng kết quả huấn luyện để tự phê duyệt.',
        'riskPercent increased without user approval': 'Tỷ lệ rủi ro tăng khi chưa có người dùng phê duyệt',
        'validation did not improve netProfitPercent by at least 10% or clearly reduce drawdown': 'Dữ liệu xác thực không cải thiện lợi nhuận ròng ít nhất 10% và cũng không giảm rõ rệt mức sụt giảm',
        'validation maxDrawdownPercent is more than 20% worse than parent': 'Mức sụt giảm trên dữ liệu xác thực xấu hơn bản gốc trên 20%',
        'validation profitFactor is lower than parent': 'Hệ số lợi nhuận trên dữ liệu xác thực thấp hơn bản gốc',
        'validation longestLossStreak is worse by more than 2 trades': 'Chuỗi lệnh thua dài nhất trên dữ liệu xác thực xấu hơn bản gốc quá 2 lệnh',
        'final test netProfitPercent is negative': 'Lợi nhuận ròng trên dữ liệu kiểm tra cuối đang âm',
        'possible overfit: improved on train but worse on validation': 'Có thể bị khớp quá mức: tốt hơn trên dữ liệu huấn luyện nhưng xấu hơn trên dữ liệu xác thực',
        'validation trade count dropped too much; winrate may be cosmetically improved': 'Số lệnh trên dữ liệu xác thực giảm quá nhiều; tỷ lệ thắng có thể chỉ đẹp hơn về hình thức',
    };
    const DEFAULT_FAVORITE_TOOLS = [];
    const PRICE_ACTION_TOOLS = new Set([
        'cursor',
        'select',
        'trendline',
        'ray',
        'hline',
        'rect',
        'ellipse',
        'long-position',
        'short-position',
        'measure',
        'text',
        'erase',
    ]);
    const ONE_CLICK_TOOLS = new Set(['hline', 'hray', 'vline', 'cross-line', 'dot', 'alert', 'price-label', 'pin', 'flag', 'icon']);
    const TEXT_TOOLS = new Set(['text', 'note', 'price-note', 'callout', 'comment', 'table']);
    const MULTI_POINT_TOOLS = new Set([
        'polyline', 'path', 'brush', 'highlighter', 'xabcd', 'abcd', 'triangle-pattern', 'three-drives',
        'head-shoulders', 'elliott-wave', 'cyclic-lines', 'pitchfork', 'inside-pitchfork', 'schiff-pitchfork',
        'modified-schiff',
    ]);
    const CHANNEL_TOOLS = new Set(['parallel-channel', 'regression-trend', 'flat-top-bottom', 'disjoint-channel', 'fib-channel']);
    const BOX_TOOLS = new Set([
        'rect', 'rotated-rect', 'circle', 'ellipse', 'triangle-shape', 'fib-circle', 'fib-wedge',
        'gann-square', 'gann-box', 'volume-profile', 'price-range', 'date-range', 'date-price-range',
    ]);
    const LINE_LIKE_TOOLS = new Set([
        'trendline', 'ray', 'extended-line', 'trend-angle', 'fib', 'fib-extension', 'fib-time-zone',
        'fib-fan', 'gann-fan', 'forecast', 'bars-pattern', 'anchored-vwap', 'arc', 'curve', 'arrow',
        'arrow-marker', 'up-arrow', 'down-arrow', 'measure',
    ]);
    const DRAWING_TOOL_LABELS = {
        cursor: 'Con trỏ',
        select: 'Quét chọn',
        'long-position': 'Vị thế mua',
        'short-position': 'Vị thế bán',
        trendline: 'Đường xu hướng',
        ray: 'Tia xu hướng',
        hline: 'Đường ngang',
        vline: 'Đường dọc',
        rect: 'Vùng giá',
        fib: 'Fibonacci',
        measure: 'Thước đo',
        text: 'Ghi chú',
        alert: 'Cảnh báo giá',
        erase: 'Xóa',
        position: 'Vị thế',
    };

    Object.assign(DRAWING_TOOL_LABELS, {
        cursor: 'Tâm ngắm',
        dot: 'Dấu chấm',
        select: 'Chọn',
        'extended-line': 'Đường kéo dài',
        hray: 'Tia ngang',
        'cross-line': 'Đường chữ thập',
        'trend-angle': 'Góc xu hướng',
        'parallel-channel': 'Kênh song song',
        'regression-trend': 'Xu hướng hồi quy',
        'flat-top-bottom': 'Kênh đỉnh/đáy ngang',
        'disjoint-channel': 'Kênh tách rời',
        pitchfork: 'Pitchfork',
        'inside-pitchfork': 'Pitchfork bên trong',
        'schiff-pitchfork': 'Pitchfork Schiff',
        'modified-schiff': 'Pitchfork Schiff điều chỉnh',
        'fib-extension': 'Fibonacci mở rộng',
        'fib-channel': 'Kênh Fibonacci',
        'fib-time-zone': 'Mốc thời gian Fibonacci',
        'fib-fan': 'Quạt Fibonacci',
        'fib-circle': 'Vòng tròn Fibonacci',
        'fib-wedge': 'Nêm Fibonacci',
        'gann-fan': 'Quạt Gann',
        'gann-square': 'Hình vuông Gann',
        'gann-box': 'Hộp Gann',
        xabcd: 'XABCD',
        abcd: 'ABCD',
        'triangle-pattern': 'Mô hình tam giác',
        'three-drives': 'Mô hình ba nhịp',
        'head-shoulders': 'Mô hình vai đầu vai',
        'elliott-wave': 'Sóng Elliott',
        'cyclic-lines': 'Các đường chu kỳ',
        forecast: 'Dự báo',
        'bars-pattern': 'Mô hình thanh giá',
        'anchored-vwap': 'VWAP có điểm neo',
        'volume-profile': 'Hồ sơ khối lượng',
        'price-range': 'Khoảng giá',
        'date-range': 'Khoảng ngày',
        'date-price-range': 'Khoảng ngày và giá',
        'rotated-rect': 'Hình chữ nhật xoay',
        circle: 'Hình tròn',
        ellipse: 'Hình elip',
        'triangle-shape': 'Hình tam giác',
        polyline: 'Đường gấp khúc',
        path: 'Đường tự do',
        arc: 'Cung tròn',
        curve: 'Đường cong',
        brush: 'Bút vẽ',
        highlighter: 'Bút đánh dấu',
        arrow: 'Mũi tên',
        'arrow-marker': 'Dấu mũi tên',
        'up-arrow': 'Mũi tên lên',
        'down-arrow': 'Mũi tên xuống',
        note: 'Ghi chú',
        'price-note': 'Ghi chú giá',
        callout: 'Chú thích nổi',
        comment: 'Bình luận',
        'price-label': 'Nhãn giá',
        table: 'Bảng',
        pin: 'Ghim',
        flag: 'Cờ đánh dấu',
        icon: 'Biểu tượng',
    });

    const state = {
        accounts: [],
        strategy: null,
        strategySummary: null,
        candidateCases: [],
        marketFiles: [],
        activeMarketFile: null,
        activeMarketData: null,
        chartView: {
            timeframe: 'H4',
            barsOnScreen: 180,
            rightOffset: 0,
            panPixelOffset: 0,
            priceScale: 1,
            priceOffset: 0,
            scaleMode: 'auto',
            timezone: 'UTC',
            chartType: 'candles',
            compareSymbols: [],
            compareData: {},
            layoutSlots: 1,
            activeLayoutName: 'Default',
            indicatorTemplate: 'default',
            indicatorColors: {},
            activeTool: 'cursor',
            pendingZoneType: null,
            drawings: loadChartDrawings(),
            drawingHistory: {},
            drawingsVisible: true,
            teachingLevelMarkersVisible: true,
            drawingsLocked: false,
            stayInDrawingMode: false,
            magnetMode: 'off',
            magnetModeExplicit: false,
            snapToOhlc: false,
            favoriteTools: loadChartFavorites(),
            favoritesVisible: false,
            copiedDrawing: null,
            drawingStyle: {
                color: '#1f6feb',
                width: 2,
                lineStyle: 'solid',
                opacity: 16,
                textSize: 12,
            },
            floatingToolbarPosition: null,
            floatingToolbarDrag: null,
            draftDrawing: null,
            selectedDrawingIds: [],
            selectionBox: null,
            timeframeSelection: null,
            crosshairPoint: null,
            candleInfoVisible: false,
            quickMenu: null,
            indicators: {
                ema20: false,
                ema50: false,
                ema200: false,
                sma100: false,
                bollinger: false,
                vwap: false,
                atrStop: false,
                rsi: false,
                macd: false,
            },
            overlays: {
                markers: true,
                slTp: true,
                positionBands: true,
                warnings: true,
                zones: true,
                swings: true,
            },
            activeRunId: 'standard',
            tradeFilter: 'all',
            selectedTradeId: null,
            highlightedZoneId: '',
            highlightedCandidateId: '',
            analyticsCache: {},
            replay: {
                enabled: false,
                playing: false,
                index: 0,
                time: null,
                speed: 1,
                timer: null,
            },
            drag: null,
            lastLayout: null,
        },
        recentReviews: [],
        stats: null,
        strategyVersions: [],
        strategyTree: null,
        strategyV2Versions: [],
        strategyV2Latest: null,
        strategyV2Production: null,
        selectedStrategyV2Id: '',
        strategyV2Loaded: false,
        strategyV2Loading: false,
        strategyV2Error: '',
        backtestRuns: [],
        backtestV2Results: [],
        selectedBacktestV2Id: '',
        selectedBacktestV2Result: null,
        selectedBacktestV2Evaluations: [],
        selectedBacktestV2Evaluation: null,
        selectedBacktestV2BestEvaluation: null,
        backtestV2FilterVersionId: '',
        backtestV2Loaded: false,
        backtestV2Loading: false,
        backtestV2Error: '',
        kpiProfiles: [],
        defaultKPIProfile: null,
        selectedKPIProfileId: '',
        kpiProfilesLoaded: false,
        kpiProfilesLoading: false,
        kpiProfilesError: '',
        experiments: [],
        experimentDetail: null,
        experimentItems: [],
        experimentBestItem: null,
        aiJobs: [],
        selectedAIJobId: '',
        aiJobsLoaded: false,
        aiJobsLoading: false,
        aiJobsError: '',
        aiWorkerRunning: false,
        researchMockRunning: false,
        autoResearchRuns: [],
        lastAutoResearchRun: null,
        autoResearchRunning: false,
        autoResearchBatch: null,
        autoResearchError: '',
        autoResearchCancelRequested: false,
        autoResearchAbortController: null,
        autoResearchProgressTimer: null,
        selectedAutoResearchRound: null,
        autoResearchSelectedSymbols: [],
        autoResearchSelectedMethods: [],
        autoResearchMethodsTouched: false,
        autoResearchSymbolsTouched: false,
        autoResearchAiProviderTouched: false,
        lastAutoResearchTournament: null,
        researchLabSessions: [],
        selectedResearchLabId: '',
        researchLabRunning: false,
        researchLabProgressTimer: null,
        researchLabRunStartedAt: 0,
        researchLabCycleStartedAt: 0,
        researchLabCurrentSessionId: '',
        researchLabRunMode: '',
        researchLabCompletedAtStart: 0,
        researchLabTargetCycles: 0,
        researchLabLastCycleMs: 0,
        researchLabStopRequested: false,
        researchLabError: '',
        promptTemplates: [],
        selectedPromptTemplateId: '',
        promptTemplatesLoaded: false,
        promptTemplatesLoading: false,
        promptTemplatesError: '',
        promptGatewaySampleVariables: '',
        promptGatewayVariablesTemplateId: '',
        promptGatewayTestResult: null,
        promptGatewayTestLoading: false,
        promptGatewayTestError: '',
        promptGatewayProvider: 'mock',
        openAiSettings: null,
        openAiSettingsLoading: false,
        openAiSettingsSaving: false,
        openAiSettingsDeleting: false,
        openAiAdminKeySaving: false,
        openAiAdminKeyDeleting: false,
        openAiOfficialCostSyncing: false,
        openAiOfficialCostError: '',
        openAiSettingsTesting: false,
        openAiSettingsError: '',
        openAiTestResult: null,
        tradebrainProfile: null,
        tradebrainMemory: [],
        aiDecisions: [],
        strategyUpgradeProposals: [],
        strategyEvaluations: [],
        aiUsage: null,
        manualReviewLabels: {},
        manualReviews: [],
        zoneManualReviews: [],
        paTrainingMode: 'zones',
        paTrainingSymbol: '',
        paTrainingTimeframe: 'H4',
        paTrainingZoneFilter: 'active',
        paTrainingZones: [],
        paTrainingTradeCases: [],
        paTrainingSelectedCaseId: '',
        paTrainingSkippedCaseIds: [],
        paTrainingQueueLoaded: false,
        paTrainingResumeLoading: false,
        paTrainingCaseLoading: false,
        paTrainingCaseError: '',
        paTrainingSelectedZoneId: '',
        paTrainingMemory: { zone_reviews: [], setup_reviews: [], lessons: [] },
        paTrainingWorkspace: {},
        paTrainingLoading: false,
        paTrainingError: '',
        paTrainingChatMessages: [],
        paTrainingChatLoading: false,
        paTrainingChatHistoryLoadingKey: '',
        paTrainingChatLoadedKey: '',
        paTrainingChatRevision: 0,
        paTrainingChatError: '',
        paActionHelpClickTimer: null,
        paTrainingSessionDrafts: {},
        paTrainingExpandedCaseId: '',
        paTrainingSessionSaving: false,
        paJournalView: 'trades',
        paJournalSearch: '',
        paJournalPeriod: '30',
        paJournalSelectedId: '',
        paJournalPage: 0,
        paTrainingChartData: null,
        paTrainingChartTimeframe: 'H4',
        paTrainingChartLoading: false,
        paTrainingChartError: '',
        paTrainingChartRequestId: 0,
        calibratedPresets: [],
        selectedStrategyVersionId: '',
        selectedExperimentId: '',
        selectedCompareEvaluationId: '',
        selectedH4ZoneId: '',
        selectedCandidateId: '',
        h4ZoneReviewFilter: 'active',
        candidateReviewReason: 'ZONE_ALREADY_BROKEN',
        calibrationQueue: [],
        calibrationQueueType: 'ZONE_ALREADY_BROKEN',
        calibrationIndex: 0,
        calibrationRun: null,
        calibrationReport: null,
        calibrationAdvisor: null,
        lastServerBacktest: null,
        lastEvolutionResult: null,
        experimentsLoaded: false,
        experimentLoading: false,
        experimentError: '',
        selectedAccountId: null,
        activeCaseIndex: 0,
        activeScore: null,
        strategyBuilderMode: 'manual',
        activeTab: 'dashboard',
    };
    applySavedChartSettings();

    const titles = {
        calibration: ['Hiệu chỉnh mẫu', 'Chấm vùng giá, mẫu ứng viên và lệnh để tạo báo cáo, sau đó mới điều chỉnh bộ luật.'],
        dashboard: ['Mục tiêu pass quỹ', 'Mục tiêu chính: pass quỹ trong 1 tháng, AI tự tìm chiến lược và cách đi vốn phù hợp.'],
        accounts: ['Tài khoản & đi vốn', 'Cấu hình quỹ, giới hạn rủi ro và lộ trình đi vốn để AI bám theo.'],
        strategy: ['Luật chiến lược', 'Tự nhập luật thủ công hoặc dùng AI nội bộ sinh bộ luật rồi backtest từng cặp.'],
        'strategy-versions': ['Phiên bản chiến lược', 'Quản lý các bản luật, phê duyệt và chọn bản dùng cho production.'],
        backtest: ['Chạy backtest', 'Chạy kiểm thử trên từng phiên bản chiến lược và phần dữ liệu đã chọn.'],
        'backtest-v2': ['Kết quả & KPI', 'Xem kết quả backtest đã lưu và đánh giá KPI tương ứng.'],
        'kpi-profiles': ['Cấu hình KPI', 'Quản lý mục tiêu tỷ lệ thắng, RR, tổng lời/tổng lỗ, sụt giảm vốn và số lệnh tối thiểu.'],
        experiments: ['Thí nghiệm AI', 'Màn nâng cao để quản lý các lần nghiên cứu, kết quả kiểm thử và phiên bản liên quan.'],
        'ai-orchestrator': ['Công cụ AI nâng cao', 'Dùng để debug prompt, worker hoặc tạo job kỹ thuật riêng. Phần AI tự sinh chiến lược nằm trong Luật chiến lược.'],
        'openai-settings': ['Kết nối OpenAI', 'Nhập API key, chọn model và đặt giới hạn chi phí mỗi ngày.'],
        'research-lab': ['AI Lab 2 tác nhân', 'Tách riêng AI nghiên cứu và máy backtest để chạy vòng lặp rõ ràng, có lịch sử và bằng chứng từng lệnh.'],
        evolution: ['TradeBrain nâng cấp', 'TradeBrain đề xuất phiên bản mới, hệ thống kiểm thử rồi đánh giá đạt hoặc không đạt.'],
        compare: ['So sánh', 'So sánh chiến lược gốc và chiến lược mới trên dữ liệu huấn luyện, xác thực và kiểm tra cuối.'],
        playbook: ['Sổ chiến lược', 'Tổng hợp phương pháp giao dịch đã khai báo để xem lại bất kỳ lúc nào.'],
        mt5: ['Dữ liệu & MT5', 'Lấy dữ liệu nến từ MT5 Exness demo để kiểm thử và huấn luyện AI trong ứng dụng.'],
        trainer: ['Dạy AI', 'Rà soát từng mẫu lệnh và lưu phản hồi của bạn.'],
        'pa-training': ['Dạy Price Action', 'Xử từng ca trade: vùng H4, setup vào lệnh, SL/TP và phản hồi AI trên cùng một màn.'],
        'pa-journal': ['Nhật ký Trade & Bài học AI', 'Theo dõi ca trade đã lưu, bài học trong bộ nhớ và các quy tắc AI có thể tham khảo khi phân tích tiếp.'],
        'tradebrain-memory': ['Bộ nhớ AI', 'Bài học và nhật ký quyết định của TradeBrain.'],
        memory: ['Nhập/xuất bộ nhớ', 'Xuất, nhập và kiểm tra lại bộ nhớ huấn luyện.'],
    };

    titles['prompt-ai'] = ['Prompt AI', 'Quản lý template prompt, phiên bản, biến bắt buộc và trạng thái kích hoạt.'];

    const BACKTEST_RUNS = [
        { id: 'fast', name: 'EMA nhanh', fast: 12, slow: 26, rr: 2.2, maxBars: 64, color: '#0ea5e9' },
        { id: 'standard', name: 'EMA chuẩn', fast: 20, slow: 50, rr: 3, maxBars: 96, color: '#1f6feb' },
        { id: 'slow', name: 'EMA chậm', fast: 30, slow: 80, rr: 3, maxBars: 132, color: '#8b5cf6' },
    ];

    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => Array.from(document.querySelectorAll(selector));
    let pinnedHelpTrigger = null;

    const HELP_SECTION_TARGETS = [
        ['#tab-dashboard .workspace-grid.two > .panel:nth-child(1) .panel-header h2', 'riskPreviewPanel'],
        ['#tab-accounts .workspace-grid.two > .panel:nth-child(2) .panel-header h2', 'accountProfilePanel'],
        ['#tab-calibration .workspace-grid.two > .panel:nth-child(1) .panel-header h2', 'calibrationQueue'],
        ['#tab-calibration .workspace-grid.two > .panel:nth-child(2) .panel-header h2', 'calibrationItem'],
        ['#tab-calibration .calibration-report-panel .panel-header h2', 'calibrationReport'],
        ['#tab-evolution .workspace-grid.two > .panel:nth-child(1) .panel-header h2', 'evolutionPanel'],
        ['#tab-compare .workspace-grid.two > .panel:nth-child(1) .panel-header h2', 'comparePanel'],
        ['#tab-playbook .playbook-head h2', 'playbookPanel'],
        ['#tab-mt5 .mt5-layout > .panel:nth-child(1) .panel-header h2', 'mt5Panel'],
        ['#mt5Form .field:nth-child(4) > span', 'mt5Timeframes'],
        ['#tab-tradebrain-memory .workspace-grid.two > .panel:nth-child(1) .panel-header h2', 'tradebrainMemoryPanel'],
    ];

    function formatDisplayLabel(value, category = '') {
        const key = String(value ?? '');
        if (!key) return '';
        if (category && UI_TRANSLATIONS[category]?.[key] !== undefined) {
            return UI_TRANSLATIONS[category][key];
        }
        if (category && UI_TRANSLATIONS[category]?.[key.toLowerCase()] !== undefined) {
            return UI_TRANSLATIONS[category][key.toLowerCase()];
        }
        for (const labels of Object.values(UI_TRANSLATIONS)) {
            if (labels[key] !== undefined) return labels[key];
            const upperKey = key.toUpperCase();
            if (labels[upperKey] !== undefined) return labels[upperKey];
            const lowerKey = key.toLowerCase();
            if (labels[lowerKey] !== undefined) return labels[lowerKey];
        }
        return key;
    }

    function translateReasonCode(value) {
        return formatDisplayLabel(value, 'reasons');
    }

    function translatePresetName(value) {
        return formatDisplayLabel(value, 'presets');
    }

    function translateStatus(value) {
        return formatDisplayLabel(value, 'statuses');
    }

    function translateReviewLabel(value) {
        return formatDisplayLabel(value, 'reviewLabels');
    }

    function translateSampleStatus(value) {
        return formatDisplayLabel(value, 'sampleStatuses');
    }

    function translateDecision(value) {
        return formatDisplayLabel(String(value ?? '').toUpperCase(), 'decisions');
    }

    function translateDisplayText(value) {
        const text = String(value ?? '');
        if (!text) return '';
        if (DISPLAY_TEXT_TRANSLATIONS[text]) return DISPLAY_TEXT_TRANSLATIONS[text];
        const mapped = formatDisplayLabel(text);
        if (mapped !== text) return mapped;
        const missingMarketData = text.match(/^Missing market data file:\s*(.+)$/i);
        if (missingMarketData) return `Thiếu tệp dữ liệu thị trường: ${missingMarketData[1]}`;
        const missingCandles = text.match(/^Missing candle data for\s+(.+)\.$/i);
        if (missingCandles) return `Thiếu dữ liệu nến cho ${missingCandles[1]}.`;
        const invalidTimestamp = text.match(/^Invalid candle timestamp at\s+(.+)\.$/i);
        if (invalidTimestamp) return `Mốc thời gian nến không hợp lệ tại ${invalidTimestamp[1]}.`;
        const duplicateTimestamps = text.match(/^Duplicate candle timestamps found for\s+(.+)\.$/i);
        if (duplicateTimestamps) return `Phát hiện mốc thời gian nến bị trùng cho ${duplicateTimestamps[1]}.`;
        const invalidOhlc = text.match(/^Invalid OHLC candle rows found for\s+(.+)\.$/i);
        if (invalidOhlc) return `Phát hiện dòng dữ liệu OHLC không hợp lệ cho ${invalidOhlc[1]}.`;
        return text
            .replace(/\bAI Trade\b/gi, 'Giao dịch AI')
            .replace(/\bmanual calibration\b/gi, 'hiệu chỉnh thủ công')
            .replace(/\bmanualReviewsTotal < 30\b/g, 'tổng số mẫu đã chấm dưới 30')
            .replace(/\bauto backtest\b/gi, 'kiểm thử tự động')
            .replace(/\bbacktest\b/gi, 'kiểm thử')
            .replace(/\bforward test demo\b/gi, 'kiểm thử tiến tới trên tài khoản demo')
            .replace(/\blive trading\b/gi, 'giao dịch thật')
            .replace(/\blive\b/gi, 'giao dịch thật')
            .replace(/\bsetup\b/gi, 'mẫu lệnh')
            .replace(/\bdataset\b/gi, 'bộ dữ liệu')
            .replace(/\btrade\b/gi, 'giao dịch')
            .replace(/\bsymbol\b/gi, 'cặp tiền')
            .replace(/\bsideway\b/gi, 'đi ngang')
            .replace(/\bsupply\b/gi, 'vùng canh sell')
            .replace(/\bdemand\b/gi, 'vùng canh buy')
            .replace(/\blimit\b/gi, 'lệnh chờ')
            .replace(/\bmarket entry\b/gi, 'vào lệnh thị trường')
            .replace(/\bentry\b/gi, 'điểm vào')
            .replace(/\bextreme\b/gi, 'biên ngoài')
            .replace(/\bswing invalidation\b/gi, 'điểm vô hiệu của nhịp giá')
            .replace(/\bpartial\b/gi, 'chốt từng phần')
            .replace(/\bsell\b/gi, 'bán')
            .replace(/\bbuy\b/gi, 'mua')
            .replace(/\bkey\b/gi, 'vùng giá quan trọng')
            .replace(/\brisk\b/gi, 'rủi ro')
            .replace(/\bequity\b/gi, 'vốn thực')
            .replace(/\bnominal balance\b/gi, 'số dư danh nghĩa')
            .replace(/\bmax loss floor\b/gi, 'ngưỡng lỗ tối đa')
            .replace(/\bbuffer\b/gi, 'biên an toàn')
            .replace(/\bcap\b/gi, 'giới hạn')
            .replace(/\bpending\b/gi, 'đang chờ')
            .replace(/\bexposure\b/gi, 'mức phơi nhiễm')
            .replace(/\brule\b/gi, 'luật')
            .replace(/\bfeedback\b/gi, 'phản hồi')
            .replace(/\bmodel\b/gi, 'mô hình')
            .replace(/\btrend\b/gi, 'xu hướng')
            .replace(/\bstructure\b/gi, 'cấu trúc')
            .replace(/\bdecision\b/gi, 'quyết định')
            .replace(/\bgolden test\b/gi, 'bộ kiểm thử chuẩn')
            .replace(/\bshadow mode\b/gi, 'chế độ chạy song song')
            .replace(/\bshadow\b/gi, 'chạy song song')
            .replace(/\bpass\b/gi, 'đạt')
            .replace(/\baccuracy\b/gi, 'độ chính xác')
            .replace(/\bvalidity\b/gi, 'tính hợp lệ')
            .replace(/\bfile\b/gi, 'tệp');
    }

    function helpData() {
        return window.AI_TRADE_HELP || {};
    }

    function helpEntry(source, key) {
        const group = helpData()[source] || {};
        return group[key] || null;
    }

    function helpTriggerHtml(key, source = 'fields', focusable = true) {
        const entry = helpEntry(source, key);
        if (!entry) return '';
        const label = entry.title || 'Giải thích';
        const interactiveAttrs = focusable ? ` role="button" tabindex="0" aria-label="${escapeAttr(`Giải thích: ${label}`)}"` : ` aria-label="${escapeAttr(`Giải thích: ${label}`)}"`;
        return `<span class="help-trigger"${interactiveAttrs} aria-expanded="false" data-help-source="${escapeAttr(source)}" data-help-key="${escapeAttr(key)}">?</span>`;
    }

    function termLabelHtml(key, label) {
        return `${escapeHtml(label)}${helpTriggerHtml(key, 'terms', false)}`;
    }

    function enhanceHelpUi() {
        enhanceFieldHelp();
        enhanceSectionHelp();
    }

    function enhanceFieldHelp() {
        const fields = helpData().fields || {};
        Object.keys(fields).forEach((id) => {
            const control = document.getElementById(id);
            if (!control) return;
            const field = control.closest('.field') || control.parentElement;
            const label = field?.querySelector(':scope > span') || field?.querySelector('span');
            appendHelpTrigger(label, id, 'fields');
        });
    }

    function enhanceSectionHelp() {
        HELP_SECTION_TARGETS.forEach(([selector, key]) => {
            const target = document.querySelector(selector);
            appendHelpTrigger(target, key, 'sections');
        });
    }

    function appendHelpTrigger(target, key, source) {
        if (!target || !helpEntry(source, key)) return;
        const exists = Array.from(target.children || []).some((child) => child.classList?.contains('help-trigger') && child.dataset.helpKey === key && child.dataset.helpSource === source);
        if (exists) return;
        target.classList.add('label-with-help');
        target.insertAdjacentHTML('beforeend', helpTriggerHtml(key, source));
    }

    function helpEventTrigger(event, selector = '.help-trigger, [data-pa-action-help]') {
        return event.target.closest?.(selector) || null;
    }

    function helpEntryForTrigger(trigger) {
        if (!trigger) return null;
        if (trigger.classList?.contains('help-trigger')) {
            return helpEntry(trigger.dataset.helpSource || 'fields', trigger.dataset.helpKey || '');
        }
        if (trigger.dataset?.paActionHelp) {
            const items = String(trigger.dataset.paActionItems || '')
                .split('|')
                .map((item) => item.trim())
                .filter(Boolean);
            return {
                title: trigger.dataset.paActionTitle || trigger.getAttribute('title') || 'Ghi chú nút',
                body: trigger.dataset.paActionHelp || '',
                items,
            };
        }
        return null;
    }

    function bindHelpEvents() {
        $('#helpGuideBtn')?.addEventListener('click', openHelpGuideModal);
        $('#helpGuideCloseBtn')?.addEventListener('click', closeHelpGuideModal);
        $('#helpGuideModal')?.addEventListener('click', (event) => {
            if (event.target?.id === 'helpGuideModal') closeHelpGuideModal();
        });

        document.addEventListener('mouseover', (event) => {
            const trigger = helpEventTrigger(event, '.help-trigger');
            if (!trigger || pinnedHelpTrigger) return;
            showHelpTooltip(trigger, false);
        });
        document.addEventListener('mouseout', (event) => {
            const trigger = helpEventTrigger(event, '.help-trigger');
            if (!trigger || pinnedHelpTrigger) return;
            const next = event.relatedTarget;
            if (next && (trigger.contains(next) || $('#helpPopover')?.contains(next))) return;
            closeHelpTooltip();
        });
        document.addEventListener('dblclick', (event) => {
            const trigger = helpEventTrigger(event, '[data-pa-action-help]');
            if (!trigger) return;
            event.preventDefault();
            event.stopPropagation();
            clearPaActionHelpClickTimer();
            if (pinnedHelpTrigger === trigger && !$('#helpPopover')?.hidden) {
                closeHelpTooltip();
            } else {
                showHelpTooltip(trigger, true);
            }
        }, true);
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest?.('.help-trigger');
            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                if (pinnedHelpTrigger === trigger && !$('#helpPopover')?.hidden) {
                    closeHelpTooltip();
                } else {
                    showHelpTooltip(trigger, true);
                }
                return;
            }
            if (!event.target.closest?.('.help-popover')) {
                closeHelpTooltip();
            }
        }, true);
        document.addEventListener('keydown', (event) => {
            const trigger = event.target.closest?.('.help-trigger');
            if (trigger && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                showHelpTooltip(trigger, true);
                return;
            }
            if (event.key === 'Escape') {
                closeHelpTooltip();
                closeHelpGuideModal();
            }
        });
        window.addEventListener('resize', () => {
            if (pinnedHelpTrigger && !$('#helpPopover')?.hidden) {
                positionHelpTooltip($('#helpPopover'), pinnedHelpTrigger);
            }
        });
    }

    function showHelpTooltip(trigger, pinned) {
        const entry = helpEntryForTrigger(trigger);
        const popover = $('#helpPopover');
        if (!entry || !popover) return;
        popover.innerHTML = helpTooltipHtml(entry);
        popover.hidden = false;
        popover.classList.add('is-visible');
        $$('.help-trigger[aria-expanded="true"], [data-pa-action-help][aria-expanded="true"]').forEach((item) => item.setAttribute('aria-expanded', 'false'));
        trigger.setAttribute('aria-expanded', 'true');
        pinnedHelpTrigger = pinned ? trigger : null;
        positionHelpTooltip(popover, trigger);
    }

    function closeHelpTooltip() {
        const popover = $('#helpPopover');
        if (popover) {
            popover.hidden = true;
            popover.classList.remove('is-visible');
        }
        pinnedHelpTrigger = null;
        $$('.help-trigger[aria-expanded="true"], [data-pa-action-help][aria-expanded="true"]').forEach((item) => item.setAttribute('aria-expanded', 'false'));
    }

    function positionHelpTooltip(popover, trigger) {
        if (!popover || !trigger) return;
        const margin = 12;
        popover.style.left = `${margin}px`;
        popover.style.top = `${margin}px`;
        const triggerRect = trigger.getBoundingClientRect();
        const popoverRect = popover.getBoundingClientRect();
        let left = triggerRect.right + 10;
        let top = triggerRect.top - 6;
        if (left + popoverRect.width > window.innerWidth - margin) {
            left = triggerRect.left - popoverRect.width - 10;
        }
        if (left < margin) {
            left = Math.min(window.innerWidth - popoverRect.width - margin, margin);
            top = triggerRect.bottom + 8;
        }
        if (top + popoverRect.height > window.innerHeight - margin) {
            top = window.innerHeight - popoverRect.height - margin;
        }
        if (top < margin) top = margin;
        popover.style.left = `${Math.max(margin, left)}px`;
        popover.style.top = `${Math.max(margin, top)}px`;
    }

    function clearPaActionHelpClickTimer() {
        if (!state.paActionHelpClickTimer) return;
        window.clearTimeout(state.paActionHelpClickTimer);
        state.paActionHelpClickTimer = null;
    }

    function runPaActionButtonClick(button, event, action) {
        if (!button?.dataset?.paActionHelp) {
            action();
            return;
        }
        if (event?.detail > 1) return;
        clearPaActionHelpClickTimer();
        state.paActionHelpClickTimer = window.setTimeout(() => {
            state.paActionHelpClickTimer = null;
            action();
        }, 240);
    }
    function helpTooltipHtml(entry) {
        return `
            <strong>${escapeHtml(entry.title || 'Giải thích')}</strong>
            ${entry.body ? `<p>${escapeHtml(entry.body)}</p>` : ''}
            ${Array.isArray(entry.items) && entry.items.length ? `<ul>${entry.items.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>` : ''}
        `;
    }

    function openHelpGuideModal() {
        const modal = $('#helpGuideModal');
        const body = $('#helpGuideBody');
        const title = $('#helpGuideTitle');
        const guide = helpData().guide || {};
        if (!modal || !body || !guide.title) return;
        const tab = state.activeTab || activeTabName();
        if (title) title.textContent = guide.title;
        body.innerHTML = helpGuideHtml(guide, tab);
        modal.hidden = false;
        document.body.classList.add('has-help-modal');
        closeHelpTooltip();
        $('#helpGuideCloseBtn')?.focus();
    }

    function closeHelpGuideModal() {
        const modal = $('#helpGuideModal');
        if (!modal || modal.hidden) return;
        modal.hidden = true;
        document.body.classList.remove('has-help-modal');
    }

    function helpGuideHtml(guide, tab) {
        const screenNotes = guide.screenNotes?.[tab] || [];
        return `
            <p class="help-guide-intro">${escapeHtml(guide.intro || '')}</p>
            <h3>Các bước nên làm</h3>
            <ol class="help-guide-steps">
                ${(guide.steps || [])
                    .map(
                        (step) => `
                            <li>
                                <strong>${escapeHtml(step.title || '')}</strong>
                                <ul>${(step.items || []).map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>
                            </li>
                        `
                    )
                    .join('')}
            </ol>
            ${
                screenNotes.length
                    ? `<div class="help-guide-screen"><h3>Gợi ý cho màn này</h3><ul>${screenNotes.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul></div>`
                    : ''
            }
        `;
    }

    function activeTabName() {
        const active = $('.nav-tab.is-active');
        return active?.dataset.tab || 'dashboard';
    }

    function displayBoolean(value) {
        return value ? 'Có' : 'Không';
    }

    function displayAdvisorValue(value) {
        if (typeof value === 'boolean') return displayBoolean(value);
        if (value === null || value === undefined || value === '') return 'Không có';
        if (typeof value === 'object') return JSON.stringify(value);
        const mapped = formatDisplayLabel(value, 'ruleValues');
        return typeof mapped === 'string' ? mapped : JSON.stringify(mapped);
    }

    function displayStrategyVersionName(value) {
        return String(value ?? '')
            .replace(/ - AI filter adjustment$/i, ' - AI điều chỉnh bộ lọc')
            .replace(/ - TradeBrain filter upgrade$/i, ' - TradeBrain nâng cấp bộ lọc');
    }

    function displayCountMap(items, category = '') {
        const entries = Object.entries(items || {});
        if (!entries.length) return 'Chưa có';
        return entries.map(([key, value]) => `${formatDisplayLabel(key, category)}: ${number(value)}`).join(', ');
    }

    function displayBacktestReason(value) {
        const reason = translateReasonCode(value);
        if (reason !== String(value ?? '')) return reason;
        return displayExitType(value);
    }

    function displayTag(value) {
        return formatDisplayLabel(value, 'tags');
    }

    async function api(action, payload, requestOptions = {}) {
        const options = requestOptions.signal ? { signal: requestOptions.signal } : {};
        let timeoutId = null;
        let didTimeout = false;
        if (!options.signal && Number(requestOptions.timeoutMs) > 0) {
            const controller = new AbortController();
            options.signal = controller.signal;
            timeoutId = setTimeout(() => { didTimeout = true; controller.abort(); }, Number(requestOptions.timeoutMs));
        }
        if (payload) {
            Object.assign(options, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        }
        let response;
        let text;
        try {
            response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
            text = await response.text();
        } catch (error) {
            if (didTimeout) throw new Error('Máy chủ web không phản hồi kịp. Key chưa được xác nhận là đã lưu; thử Tải lại trạng thái trước khi gửi lại.');
            if (error instanceof TypeError || /failed to fetch|networkerror|load failed/i.test(String(error?.message || ''))) {
                const apiPath = new URL('api.php', document.baseURI).pathname;
                throw new Error(`Không kết nối được API của website (${apiPath}). Máy chủ cần chạy PHP và truy cập được api.php; đây chưa phải lỗi API key.`);
            }
            throw error;
        } finally {
            if (timeoutId !== null) clearTimeout(timeoutId);
        }
        let data = {};
        try {
            data = text ? JSON.parse(text) : {};
        } catch (error) {
            const preview = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
            throw new Error(preview ? `API trả về dữ liệu lỗi: ${preview}` : 'API không trả về JSON hợp lệ');
        }
        if (!response.ok || data.ok === false) {
            throw new Error(data.error || 'Yêu cầu bị lỗi');
        }
        return data;
    }

    async function bootstrap() {
        setStatus('Đang tải');
        const data = await api('bootstrap');
        let rememberedPaWorkspace = null;
        state.accounts = data.accounts || [];
        state.strategy = data.strategy || {};
        state.strategySummary = data.strategy_summary || {};
        state.candidateCases = data.candidate_cases || [];
        state.marketFiles = data.market_files || [];
        state.recentReviews = data.recent_reviews || [];
        state.stats = data.stats || {};
        state.strategyVersions = data.strategy_versions || [];
        state.strategyTree = data.strategy_tree || null;
        state.backtestRuns = data.backtest_runs || [];
        if (Array.isArray(data.experiments)) {
            state.experiments = data.experiments;
            state.experimentsLoaded = true;
        }
        state.tradebrainProfile = data.tradebrain_profile || null;
        state.tradebrainMemory = data.tradebrain_memory || [];
        state.aiDecisions = data.ai_decisions || [];
        state.strategyUpgradeProposals = data.strategy_upgrade_proposals || [];
        state.strategyEvaluations = data.strategy_evaluations || [];
        state.aiUsage = data.ai_usage || null;
        state.autoResearchRuns = data.auto_research_runs || [];
        state.researchLabSessions = data.research_lab_sessions || [];
        state.selectedResearchLabId = state.selectedResearchLabId || state.researchLabSessions[0]?.id || '';
        state.openAiSettings = data.openai_local_settings || null;
        state.manualReviewLabels = data.manual_review_labels || {};
        state.manualReviews = data.manual_reviews || [];
        state.zoneManualReviews = data.zone_manual_reviews || [];
        state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
        state.paTrainingWorkspace = data.pa_training_workspace && typeof data.pa_training_workspace === 'object' ? data.pa_training_workspace : {};
        rememberedPaWorkspace = restorePaWorkspaceSelection();
        state.calibratedPresets = data.calibrated_presets || [];
        state.selectedStrategyVersionId = state.selectedStrategyVersionId || (state.strategyVersions[0] && state.strategyVersions[0].id) || '';
        state.selectedAccountId = state.selectedAccountId || (state.accounts[0] && state.accounts[0].id);
        state.activeCaseIndex = Math.min(state.activeCaseIndex, Math.max(0, state.candidateCases.length - 1));
        state.activeScore = null;
        renderAll();
        activateTabFromHash();
        setStatus('Sẵn sàng');
        autoSyncOpenAiOfficialCosts();
        if (state.activeTab === 'pa-training' && rememberedPaWorkspace?.symbol) {
            resumePaTrainingWorkspace(rememberedPaWorkspace).catch(() => {});
        }
    }

    async function resumePaTrainingWorkspace(remembered = savedPaWorkspace()) {
        if (state.paTrainingResumeLoading) return;
        const symbol = remembered.symbol || state.paTrainingSymbol;
        const timeframe = remembered.timeframe || state.paTrainingTimeframe || 'H4';
        const requestedChartTimeframe = String(remembered.chartTimeframe || timeframe || 'H4').toUpperCase();
        const chartFile = symbol ? findBestMarketFileForSymbol(symbol, requestedChartTimeframe) : null;
        if (!chartFile) return;
        state.paTrainingResumeLoading = true;
        try {
            const chartTimeframe = String(chartFile.timeframe || requestedChartTimeframe).toUpperCase();
            state.paTrainingSymbol = symbol;
            state.paTrainingTimeframe = timeframe;
            state.paTrainingChartTimeframe = chartTimeframe;
            state.paTrainingSelectedCaseId = remembered.selectedCaseId || '';
            state.paTrainingSkippedCaseIds = Array.isArray(remembered.skippedCaseIds) ? remembered.skippedCaseIds.slice(0, 500) : [];
            await loadPaTrainingTradeCases({ silent: true, restoreProgress: true, chartTimeframe });
            if (!state.activeMarketData || !sameSymbol(state.activeMarketData.symbol, symbol)
                || String(state.activeMarketData.timeframe || '').toUpperCase() !== chartTimeframe) {
                await loadPaTrainingChartForSymbol(symbol, chartTimeframe, { silent: true });
            }
            if (state.activeMarketData && sameSymbol(state.activeMarketData.symbol, symbol)
                && String(state.activeMarketData.timeframe || '').toUpperCase() === chartTimeframe) {
                restorePaTrainingChartViewport(state.activeMarketData, remembered);
                persistPaWorkspace({ chartTimeframe, selectedCaseId: state.paTrainingSelectedCaseId });
            }
        } finally {
            state.paTrainingResumeLoading = false;
        }
    }

    function renderAll() {
        renderMetrics();
        renderFundingGoalPlan();
        renderRiskPreview();
        renderRecentReviews();
        renderAccounts();
        renderStrategy();
        renderStrategyBuilderMode();
        renderBacktestLab();
        renderStrategyV2Manager();
        renderBacktestV2Results();
        renderKPIProfiles();
        renderExperiments();
        renderAIJobs();
        renderAutoResearch();
        renderResearchLab();
        renderOpenAiSettings();
        renderPromptTemplates();
        renderCalibrationReview();
        renderStrategyEvolution();
        renderStrategyCompare();
        renderPlaybook();
        renderMt5();
        renderPaTraining();
        renderTrainer();
        renderTradeBrainMemory();
        renderMemory();
        enhanceHelpUi();
    }

    function renderMetrics() {
        const stats = state.stats || {};
        const cases = state.candidateCases.length;
        const symbols = (state.strategy.symbols || []).length;
        const html = [
            metric('Đã rà soát', number(stats.total_reviewed || 0), 'Tình huống phản hồi đã lưu'),
            metric('Đã duyệt', number(stats.approved || 0), 'Mẫu lệnh được chấp nhận'),
            metric('Đã bỏ', number(stats.rejected || 0), 'Mẫu lệnh bị loại'),
            metric('Cặp tiền', number(symbols), `${cases} tình huống phát lại`),
        ].join('');
        $('#metricGrid').innerHTML = html;
    }

    function metric(label, value, sub) {
        return `<div class="metric"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong><span>${escapeHtml(sub)}</span></div>`;
    }

    function renderFundingGoalPlan() {
        const target = $('#fundingGoalPlan');
        if (!target) return;
        const account = selectedAccount();
        if (!account) {
            target.innerHTML = '<div class="notice">Chưa có hồ sơ tài khoản. Tạo hồ sơ quỹ ở tab Tài khoản trước.</div>';
            return;
        }
        const plan = fundingGoalPlan(account);
        target.innerHTML = `
            <div class="funding-goal-grid">
                ${fundingGoalCard('Mục tiêu quỹ', `+${formatFixed(plan.targetPct, 2)}%`, `${money(plan.targetUsd)} trong ${number(plan.days)} ngày`)}
                ${fundingGoalCard('Nhịp cần đạt', money(plan.dailyTargetUsd), `khoảng ${formatFixed(plan.dailyTargetPct, 2)}% / ngày giao dịch`)}
                ${fundingGoalCard('Risk mỗi lệnh', money(plan.riskPerTradeUsd), `${formatFixed(plan.riskPerTradeAccountPct, 3)}% tài khoản | ${formatFixed(plan.riskPct, 2)}% risk-buffer`)}
                ${fundingGoalCard('Dừng ngày', money(plan.dailyStopUsd), `tối đa khoảng ${formatFixed(plan.maxLossTradesBeforeStop, 1)} lệnh thua`)}
            </div>
            <div class="funding-goal-brief">
                <div>
                    <strong>Việc giao cho AI</strong>
                    <span>AI tự xây chiến lược đạt KPI backtest/forward, ưu tiên RR >= 2, PF >= 1.5, DD thấp và đủ số lệnh. Không đặt lệnh thật khi chưa duyệt.</span>
                </div>
                <div>
                    <strong>Cách đi vốn mặc định</strong>
                    <span>${escapeHtml(plan.capitalRule)}</span>
                </div>
                <div>
                    <strong>Luật an toàn</strong>
                    <span>Dừng ngày khi chạm giới hạn, giảm risk khi equity đi xuống, không cố gỡ sau chuỗi SL, không vượt luật daily/max loss của quỹ.</span>
                </div>
            </div>
        `;
    }

    function fundingGoalPlan(account) {
        const initial = Number(account.initial_balance || 0);
        const targetPct = Number(account.challenge_target_pct ?? 8);
        const days = Math.max(1, intNum(account.challenge_days ?? 30));
        const tradingDays = Math.max(1, intNum(account.challenge_trading_days ?? 20));
        const riskPct = Number(account.risk_per_trade_pct ?? 1.5);
        const maxLossPct = Number(account.max_loss_pct ?? 10);
        const capPct = account.risk_base_cap_pct === null || account.risk_base_cap_pct === undefined ? maxLossPct : Number(account.risk_base_cap_pct);
        const survivalReservePct = Number(account.survival_reserve_pct ?? 20);
        const dailyReservePct = Number(account.daily_reserve_pct ?? 20);
        const maxLossUsd = initial * maxLossPct / 100;
        const cappedRiskBase = account.risk_model === 'prop_buffer_capped'
            ? Math.min(maxLossUsd, initial * capPct / 100)
            : initial;
        const survivalReserveUsd = account.risk_model === 'prop_buffer_capped'
            ? maxLossUsd * survivalReservePct / 100
            : 0;
        const riskBase = Math.max(0, cappedRiskBase - survivalReserveUsd);
        const riskPerTradeUsd = riskBase * riskPct / 100;
        const dailyStopPct = Number(account.daily_stop_pct ?? 1.5);
        const targetUsd = initial * targetPct / 100;
        const dailyTargetUsd = targetUsd / tradingDays;
        const dailyStopUsd = initial * dailyStopPct / 100;
        const riskPerTradeAccountPct = initial > 0 ? riskPerTradeUsd / initial * 100 : 0;
        const dailyTargetPct = targetPct / tradingDays;
        const maxLossTradesBeforeStop = riskPerTradeUsd > 0 ? dailyStopUsd / riskPerTradeUsd : 0;
        return {
            initial,
            targetPct,
            targetUsd,
            days,
            tradingDays,
            dailyTargetUsd,
            dailyTargetPct,
            riskPct,
            riskPerTradeUsd,
            riskPerTradeAccountPct,
            dailyStopUsd,
            maxLossTradesBeforeStop,
            survivalReservePct,
            dailyReservePct,
            survivalReserveUsd,
            capitalRule: `Mỗi lệnh chỉ dùng phần risk-buffer sau khi chừa ${formatFixed(survivalReservePct, 0)}% max loss làm đệm sống còn. Vol chạy đủ khi buffer còn khỏe; nếu buffer giảm thì tự hạ còn 75% / 50% / 25%, và dừng hẳn khi chạm vùng nguy hiểm. Với cấu hình hiện tại, risk ban đầu khoảng ${money(riskPerTradeUsd)} (${formatFixed(riskPerTradeAccountPct, 3)}% tài khoản); nếu âm ${money(dailyStopUsd)} hoặc thua ${Math.max(1, Math.floor(maxLossTradesBeforeStop))} lệnh thì nghỉ, vẫn chừa ${formatFixed(dailyReservePct, 0)}% biên dừng ngày.`
        };
    }

    function fundingGoalCard(label, value, sub) {
        return `
            <div class="funding-goal-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <small>${escapeHtml(sub)}</small>
            </div>
        `;
    }
    function renderRiskPreview() {
        const account = selectedAccount();
        if (!account) {
            $('#riskPreviewForm').innerHTML = '';
            $('#riskOutput').innerHTML = '';
            return;
        }
        $('#riskAccountName').textContent = account.name;
        $('#riskPreviewForm').innerHTML = `
                ${fieldHtml('Vốn thực hiện tại', 'riskEquity', account.initial_balance, 'number')}
            ${fieldHtml('Số dư đầu ngày', 'riskDayBalance', account.initial_balance, 'number')}
            ${fieldHtml('Vốn thực đầu ngày', 'riskDayEquity', account.initial_balance, 'number')}
        `;
        ['riskEquity', 'riskDayBalance', 'riskDayEquity'].forEach((id) => {
            document.getElementById(id).addEventListener('input', updateRiskOutput);
        });
        updateRiskOutput();
    }

    async function updateRiskOutput() {
        const account = selectedAccount();
        if (!account) return;
        const payload = {
            account,
            equity: num($('#riskEquity').value),
            day_start_balance: num($('#riskDayBalance').value),
            day_start_equity: num($('#riskDayEquity').value),
        };
        try {
            const data = await api('risk_preview', payload);
            const risk = data.risk;
            $('#riskOutput').innerHTML = [
                riskRow('Trạng thái đi vốn', survivalRiskStatusLabel(risk.risk_status)),
                riskRow('Mức lỗ tối đa', money(risk.absolute_floor)),
                riskRow('Biên an toàn còn lại', money(risk.current_buffer)),
                riskRow('Đệm không được dùng', money(risk.survival_reserve)),
                riskRow('Vốn còn được phép risk', money(risk.tradable_buffer)),
                riskRow('Hệ số giảm vol', `${formatFixed((risk.risk_multiplier ?? 1) * 100, 0)}%`),
                riskRow('Risk % hiệu dụng', `${formatFixed(risk.effective_risk_pct ?? 0, 3)}% risk-buffer`),
                riskRow('Rủi ro mỗi lệnh sau giảm vol', money(risk.risk_per_trade)),
                riskRow('Sàn dừng ngày', money(risk.bot_daily_floor)),
                riskRow('Đệm dừng ngày', money(risk.daily_reserve)),
                riskRow('Mức rủi ro được phép lúc này', money(risk.risk_allowed_now)),
            ].join('');
        } catch (error) {
            showToast(error.message);
        }
    }

    function survivalRiskStatusLabel(status) {
        const normalized = String(status || '').toUpperCase();
        if (normalized === 'HALTED') return 'Dừng trade - bảo vệ tài khoản';
        if (normalized === 'REDUCED') return 'Giảm vol tự động';
        return 'Bình thường';
    }
    function riskRow(label, value) {
        return `<div class="risk-row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
    }

    function renderRecentReviews() {
        $('#recentCount').textContent = `${state.recentReviews.length}`;
        if (!state.recentReviews.length) {
            $('#recentReviews').innerHTML = '<div class="review-item"><span>Chưa có lần rà soát nào được lưu.</span></div>';
            return;
        }
        $('#recentReviews').innerHTML = state.recentReviews
            .map((review) => {
                const c = review.case || {};
                return `
                    <div class="review-item">
                        <strong>${escapeHtml(c.symbol || 'UNKNOWN')} ${escapeHtml(displayDirection(c.direction))}</strong>
                        <span>${escapeHtml(displayReviewDecision(review.your_decision))} | ${escapeHtml(displaySetup(c.setup_type))} | ${escapeHtml(review.stored_at || '')}</span>
                    </div>
                `;
            })
            .join('');
    }

    function renderAccounts() {
        const list = $('#accountList');
        list.innerHTML = state.accounts
            .map((account) => {
                const active = account.id === state.selectedAccountId ? ' is-active' : '';
                return `
                    <div class="profile-item${active}" data-account-id="${escapeAttr(account.id)}">
                        <strong>${escapeHtml(account.name)}</strong>
                        <span>${escapeHtml(displayAccountType(account.account_type))} | ${money(account.initial_balance)} | ${escapeHtml(displayRiskModel(account.risk_model))}</span>
                    </div>
                `;
            })
            .join('');

        $$('.profile-item').forEach((item) => {
            item.addEventListener('click', () => {
                state.selectedAccountId = item.dataset.accountId;
                renderAll();
            });
        });

        const account = selectedAccount();
        $('#accountForm').innerHTML = account ? accountFormHtml(account) : '';
    }

    function accountFormHtml(account) {
        return [
            fieldHtml('Tên hồ sơ', 'acc_name', account.name),
            fieldHtml('Mã tài khoản', 'acc_id', account.id),
            selectHtml('Loại tài khoản', 'acc_account_type', account.account_type, ['prop_firm', 'personal']),
            fieldHtml('Sàn giao dịch', 'acc_broker', account.broker || ''),
            fieldHtml('Vốn ban đầu', 'acc_initial_balance', account.initial_balance, 'number'),
            fieldHtml('Tiền tệ', 'acc_currency', account.currency || 'USD'),
            selectHtml('Cách tính rủi ro', 'acc_risk_model', account.risk_model, ['prop_buffer_capped', 'equity_percent', 'fixed_usd']),
            sectionTitleHtml('Mục tiêu pass quỹ', 'AI dùng các thông số này để tự xây chiến lược và kế hoạch đi vốn.'),
            fieldHtml('Mục tiêu lợi nhuận %', 'acc_challenge_target_pct', account.challenge_target_pct ?? 8, 'number'),
            fieldHtml('Thời gian pass (ngày)', 'acc_challenge_days', account.challenge_days ?? 30, 'number'),
            fieldHtml('Số ngày giao dịch dự kiến', 'acc_challenge_trading_days', account.challenge_trading_days ?? 20, 'number'),
            sectionTitleHtml('Luật rủi ro quỹ', 'Risk/lệnh đang tính trên risk-buffer với mô hình prop_buffer_capped.'),
            fieldHtml('Mức lỗ tối đa %', 'acc_max_loss_pct', account.max_loss_pct, 'number'),
            fieldHtml('Giới hạn lỗ trong ngày %', 'acc_daily_loss_hard_pct', account.daily_loss_hard_pct, 'number'),
            fieldHtml('Bot dừng ngày %', 'acc_daily_stop_pct', account.daily_stop_pct, 'number'),
            fieldHtml('Giới hạn vốn tính rủi ro %', 'acc_risk_base_cap_pct', account.risk_base_cap_pct, 'number'),
            fieldHtml('Rủi ro mỗi lệnh %', 'acc_risk_per_trade_pct', account.risk_per_trade_pct, 'number'),
            fieldHtml('Đệm max loss không được dùng %', 'acc_survival_reserve_pct', account.survival_reserve_pct ?? 20, 'number'),
            fieldHtml('Đệm dừng ngày không được dùng %', 'acc_daily_reserve_pct', account.daily_reserve_pct ?? 20, 'number'),
            fieldHtml('Lệnh mở tối đa', 'acc_max_open_trades', account.max_open_trades, 'number'),
            fieldHtml('Tối đa cùng đồng tiền', 'acc_max_same_currency_exposure', account.max_same_currency_exposure, 'number'),
            fieldHtml('Lệnh thua tối đa/ngày', 'acc_max_losing_trades_per_day', account.max_losing_trades_per_day, 'number'),
            fieldHtml('Dừng sau chuỗi SL', 'acc_stop_after_consecutive_sl', account.stop_after_consecutive_sl, 'number'),
            checkboxHtml('Cho giữ qua đêm', 'acc_allow_overnight_hold', account.allow_overnight_hold),
            checkboxHtml('Cho giữ cuối tuần', 'acc_allow_weekend_hold', account.allow_weekend_hold),
            fieldHtml('Đóng trước cuối tuần (giờ)', 'acc_friday_close_hours_before_market_close', account.friday_close_hours_before_market_close, 'number'),
        ].join('');
    }

    function readAccountForm() {
        return {
            id: slug($('#acc_id').value || $('#acc_name').value),
            name: $('#acc_name').value.trim(),
            account_type: $('#acc_account_type').value,
            platform: 'MT5',
            broker: $('#acc_broker').value.trim(),
            initial_balance: num($('#acc_initial_balance').value),
            currency: $('#acc_currency').value.trim() || 'USD',
            risk_model: $('#acc_risk_model').value,
            challenge_target_pct: num($('#acc_challenge_target_pct').value),
            challenge_days: intNum($('#acc_challenge_days').value),
            challenge_trading_days: intNum($('#acc_challenge_trading_days').value),
            max_loss_pct: nullableNum($('#acc_max_loss_pct').value),
            daily_loss_hard_pct: nullableNum($('#acc_daily_loss_hard_pct').value),
            daily_stop_pct: num($('#acc_daily_stop_pct').value),
            risk_base_cap_pct: nullableNum($('#acc_risk_base_cap_pct').value),
            risk_per_trade_pct: num($('#acc_risk_per_trade_pct').value),
            survival_reserve_pct: num($('#acc_survival_reserve_pct').value),
            daily_reserve_pct: num($('#acc_daily_reserve_pct').value),
            max_open_trades: intNum($('#acc_max_open_trades').value),
            max_same_currency_exposure: intNum($('#acc_max_same_currency_exposure').value),
            max_losing_trades_per_day: intNum($('#acc_max_losing_trades_per_day').value),
            stop_after_consecutive_sl: intNum($('#acc_stop_after_consecutive_sl').value),
            server_timezone: 'MT5_SERVER_TIME',
            allow_overnight_hold: $('#acc_allow_overnight_hold').checked,
            allow_weekend_hold: $('#acc_allow_weekend_hold').checked,
            friday_close_hours_before_market_close: intNum($('#acc_friday_close_hours_before_market_close').value),
        };
    }

    function renderStrategy() {
        const strategy = state.strategy || {};
        $('#strategyForm').innerHTML = [
            fieldHtml('Tên chiến lược', 'strat_name', strategy.name || ''),
            helperFieldHtml(
                'Ngưỡng điểm AI tối thiểu',
                'strat_min_confidence',
                strategy.trend?.min_confidence ?? 65,
                'number',
                'Thang 0-100. Mẫu lệnh dưới ngưỡng này sẽ được coi là chưa đủ rõ để AI đề xuất vào lệnh.'
            ),
            fieldHtml('RR tối thiểu', 'strat_min_rr', strategy.risk_reward?.min_rr ?? 3, 'number'),
            fieldHtml('Số vùng TP H4 cần xét', 'strat_h4_tp_candidates', strategy.zones?.h4_tp_candidates ?? 2, 'number'),
            sectionTitleHtml('Quản lý lệnh', 'Các rule này dùng sau khi lệnh đã khớp, gồm dời BE và thoát theo cấu trúc M15.'),
            checkboxHtml('Bật dời SL về điểm vào', 'strat_be_enabled', strategy.management?.be_enabled ?? true),
            fieldHtml('R dời BE thấp nhất', 'strat_be_trigger_r_min', strategy.management?.be_trigger_r_min ?? 1.8, 'number'),
            fieldHtml('R dời BE cao nhất', 'strat_be_trigger_r_max', strategy.management?.be_trigger_r_max ?? 2, 'number'),
            checkboxHtml('Không chốt từng phần', 'strat_no_partial_close', !(strategy.management?.partial_close ?? false)),
            fieldHtml('Khối lượng đóng khi thoát %', 'strat_close_size_pct', strategy.management?.close_size_pct ?? 100, 'number'),
            checkboxHtml('Thoát khi M15 đảo cấu trúc', 'strat_exit_on_m15_opposite_structure', strategy.management?.exit_on_m15_opposite_structure ?? true),
            checkboxHtml('Thoát khi M15 đi ngang rồi phá ngược', 'strat_exit_on_m15_sideway_break_against_trade', strategy.management?.exit_on_m15_sideway_break_against_trade ?? true),
            checkboxHtml('Thoát tại vùng TP H4 nếu M15 phản ứng ngược', 'strat_exit_at_h4_tp_zone_on_m15_reversal', strategy.management?.exit_at_h4_tp_zone_on_m15_reversal ?? true),
            helperSelectHtml(
                'Vùng chờ hồi để thoát',
                'strat_exit_retest_level',
                strategy.management?.exit_retest_level ?? 'broken_m15_key_level',
                ['broken_m15_key_level'],
                'Mặc định: chờ giá hồi lại vùng key M15 vừa bị phá rồi đóng toàn bộ lệnh.'
            ),
            sectionTitleHtml('Bộ lọc vào lệnh', 'Tin tức, spread và các điều kiện chặn trước khi đặt lệnh chờ.'),
            fieldHtml('Chặn trước tin (phút)', 'strat_news_before', strategy.filters?.block_red_news_minutes_before ?? 60, 'number'),
            fieldHtml('Chặn sau tin (phút)', 'strat_news_after', strategy.filters?.block_red_news_minutes_after ?? 60, 'number'),
            textareaHtml('Cặp tiền', 'strat_symbols', (strategy.symbols || []).join(', ')),
            textareaHtml('Ghi chú', 'strat_notes', strategy.description || ''),
        ].join('');

        const entries = strategy.entries || [];
        $('#setupCount').textContent = `${entries.length}`;
        $('#setupList').innerHTML = entries
            .map((entry) => {
                return `
                    <div class="setup-item">
                        <strong>${escapeHtml(translateDisplayText(entry.name))}</strong>
                        <span>${escapeHtml(translateDisplayText(entry.description || ''))}</span>
                    </div>
                `;
            })
            .join('');

        $('#managementList').innerHTML = managementSummaryHtml(strategy);
        renderStrategyVersions();
    }

    function renderStrategyBuilderMode() {
        const mode = state.strategyBuilderMode === 'ai' ? 'ai' : 'manual';
        $$('[data-strategy-builder-mode]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.strategyBuilderMode === mode);
            button.setAttribute('aria-selected', button.dataset.strategyBuilderMode === mode ? 'true' : 'false');
        });
        $$('[data-strategy-builder-panel]').forEach((panel) => {
            const active = panel.dataset.strategyBuilderPanel === mode;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
    }

    function renderStrategyVersions() {
        const list = $('#strategyVersionList');
        const detail = $('#strategyVersionDetail');
        const compact = $('#strategyVersionCompactSummary');
        if (!list) return;
        const versions = state.strategyVersions || [];
        if (!versions.length) {
            if (compact) {
                compact.innerHTML = `
                    <article class="strategy-version-current-card is-empty">
                        <strong>Chưa có phiên bản chiến lược</strong>
                        <span>Mở phần quản lý chi tiết để tạo bản v1 từ luật hiện tại.</span>
                    </article>
                `;
            }
            list.innerHTML = '<div class="notice">Chưa có phiên bản chiến lược. Bấm tạo chiến lược v1 để sao chép chiến lược hiện tại.</div>';
            detail.innerHTML = '<div class="notice">Chiến lược gốc sẽ không bị ghi đè.</div>';
            return;
        }

        if (!state.selectedStrategyVersionId || !versions.some((item) => item.id === state.selectedStrategyVersionId)) {
            state.selectedStrategyVersionId = versions[0].id;
        }
        const selected = selectedStrategyVersion();
        if (compact) {
            compact.innerHTML = strategyVersionCompactSummaryHtml(selected, versions.length);
        }
        list.innerHTML = versions
            .map((version) => {
                const active = version.id === state.selectedStrategyVersionId ? ' is-active' : '';
                const parent = version.parentVersionId ? `Chiến lược gốc: ${version.parentVersionId}` : 'Bản gốc';
                const rules = version.rulesJson || {};
                return `
                    <button class="profile-item strategy-version-item${active}" type="button" data-strategy-version-id="${escapeAttr(version.id)}">
                        <div class="strategy-version-card-head">
                            <strong>${escapeHtml(displayStrategyVersionName(version.name))}</strong>
                            <span class="status-badge">${escapeHtml(translateStatus(version.status))}</span>
                        </div>
                        <span>${escapeHtml(strategyRulesOneLine(rules))}</span>
                        <span>${escapeHtml(translateStatus(version.createdBy))} | ${escapeHtml(parent)}</span>
                    </button>
                `;
            })
            .join('');

        $$('.strategy-version-item').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedStrategyVersionId = button.dataset.strategyVersionId;
                renderAll();
            });
        });

        if (!selected) {
            detail.innerHTML = '';
            return;
        }

        const children = versions.filter((version) => version.parentVersionId === selected.id);
        detail.innerHTML = `
            <div class="version-detail-head">
                <div>
                    <h3>${escapeHtml(displayStrategyVersionName(selected.name))}</h3>
                    <span>${escapeHtml(selected.id)} | ${escapeHtml(translateStatus(selected.status))}</span>
                </div>
                <div class="topbar-actions">
                    <button class="button secondary" data-version-status="approved" type="button">Phê duyệt</button>
                    <button class="button danger" data-version-status="rejected" type="button">Từ chối</button>
                    <button class="button secondary" data-version-status="retired" type="button">Ngừng sử dụng</button>
                </div>
            </div>
            ${strategyVersionOverviewHtml(selected, children)}
            <div class="strategy-version-result-grid">
                ${strategyVersionResultHtml('Kiểm thử', selected.backtestSummary)}
                ${strategyVersionResultHtml('Xác thực', selected.validationSummary)}
                ${strategyVersionResultHtml('Kiểm tra cuối', selected.finalTestSummary)}
            </div>
            <details class="json-details">
                <summary>Chi tiết kỹ thuật: dữ liệu bộ luật (rulesJson)</summary>
                <pre>${escapeHtml(JSON.stringify(selected.rulesJson || {}, null, 2))}</pre>
            </details>
            <details class="json-details">
                <summary>Ghi chú</summary>
                <pre>${escapeHtml(translateDisplayText(selected.notes || selected.approvalNotes || ''))}</pre>
            </details>
        `;
        detail.querySelectorAll('[data-version-status]').forEach((button) => {
            button.addEventListener('click', () => updateStrategyVersionStatus(selected.id, button.dataset.versionStatus).catch((e) => showToast(e.message)));
        });
    }

    function strategyVersionCompactSummaryHtml(version, totalVersions) {
        const rules = version.rulesJson || {};
        const latestSummary = version.finalTestSummary || version.validationSummary || version.backtestSummary || null;
        const resultText = latestSummary
            ? `${formatSigned(latestSummary.netProfitPercent || 0, 2)}% | ${number(latestSummary.totalTrades || 0)} lệnh | WR ${formatFixed(latestSummary.winRate || 0, 2)}%`
            : 'Chưa có backtest';
        return `
            <article class="strategy-version-current-card">
                <div class="strategy-version-current-main">
                    <div>
                        <span class="strategy-version-current-label">Bản đang chọn</span>
                        <h3>${escapeHtml(displayStrategyVersionName(version.name))}</h3>
                        <p>${escapeHtml(version.id)} | ${escapeHtml(translateStatus(version.status))} | ${number(totalVersions)} phiên bản</p>
                    </div>
                    <span class="status-badge">${strategyVersionReadyForAi(version) ? 'Sẵn sàng cho AI' : 'Cần kiểm tra thêm'}</span>
                </div>
                <div class="strategy-version-current-stats">
                    ${strategyVersionCompactMetricHtml('Luật', strategyRulesOneLine(rules))}
                    ${strategyVersionCompactMetricHtml('Hướng lệnh', strategyDirectionLabel(rules.directionAllowed || 'BUY_SELL'))}
                    ${strategyVersionCompactMetricHtml('Kết quả gần nhất', resultText)}
                    ${strategyVersionCompactMetricHtml('Nguồn tạo', translateStatus(version.createdBy || 'user'))}
                </div>
            </article>
        `;
    }

    function strategyVersionCompactMetricHtml(label, value) {
        return `
            <div class="strategy-version-current-stat">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
            </div>
        `;
    }

    function strategyVersionOverviewHtml(version, children) {
        const rules = version.rulesJson || {};
        return `
            <section class="strategy-version-overview">
                <div class="strategy-version-summary-card">
                    <span>Cặp tiền</span>
                    <strong>${escapeHtml(strategySymbolsSummary(rules.symbols || []))}</strong>
                </div>
                <div class="strategy-version-summary-card">
                    <span>Khung chính</span>
                    <strong>${escapeHtml(rules.contextTimeframe || 'H4')}</strong>
                </div>
                <div class="strategy-version-summary-card">
                    <span>Khung vào lệnh</span>
                    <strong>${escapeHtml(rules.entryTimeframe || 'M15')}</strong>
                </div>
                <div class="strategy-version-summary-card">
                    <span>RR tối thiểu</span>
                    <strong>${escapeHtml(formatFixed(rules.minRR ?? rules.minRr ?? 0, 2))}</strong>
                </div>
                <div class="strategy-version-summary-card">
                    <span>Rủi ro mỗi lệnh</span>
                    <strong>${escapeHtml(formatPercentValue(rules.riskPercent ?? rules.maxRiskPercent ?? 0))}</strong>
                </div>
                <div class="strategy-version-summary-card">
                    <span>Sẵn sàng cho AI</span>
                    <strong>${strategyVersionReadyForAi(version) ? 'Có' : 'Cần kiểm tra thêm'}</strong>
                </div>
            </section>
            <div class="memory-summary strategy-version-meta">
                ${memoryRow('Chiến lược gốc', version.parentVersionId || 'Không có')}
                ${memoryRow('Các phiên bản con', children.length ? children.map((child) => displayStrategyVersionName(child.name)).join(', ') : 'Không có')}
                ${memoryRow('Hướng lệnh', strategyDirectionLabel(rules.directionAllowed || 'BUY_SELL'))}
                ${memoryRow('Nguồn tạo', translateStatus(version.createdBy || 'user'))}
            </div>
        `;
    }

    function strategyVersionResultHtml(label, summary) {
        if (!summary) {
            return `
                <div class="strategy-version-result-card">
                    <span>${escapeHtml(label)}</span>
                    <strong>Chưa có</strong>
                    <small>Chưa chạy backtest cho phần dữ liệu này.</small>
                </div>
            `;
        }

        return `
            <div class="strategy-version-result-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(formatSigned(summary.netProfitPercent || 0, 2))}%</strong>
                <small>${number(summary.totalTrades || 0)} lệnh | WR ${formatFixed(summary.winRate || 0, 2)}%</small>
            </div>
        `;
    }

    function strategyRulesOneLine(rules) {
        return `${strategySymbolsSummary(rules.symbols || [])} | ${rules.contextTimeframe || 'H4'} -> ${rules.entryTimeframe || 'M15'} | RR ${formatFixed(rules.minRR ?? rules.minRr ?? 0, 2)}`;
    }

    function strategySymbolsSummary(symbols) {
        const list = Array.isArray(symbols) ? symbols.filter(Boolean).map(String) : [];
        if (!list.length) return 'Chưa chọn cặp';
        const shown = list.slice(0, 4).join(', ');
        return list.length > 4 ? `${shown} +${list.length - 4} cặp` : shown;
    }

    function strategyDirectionLabel(value) {
        const key = String(value || '').toUpperCase();
        if (key === 'BUY_ONLY') return 'Chỉ mua';
        if (key === 'SELL_ONLY') return 'Chỉ bán';
        if (key === 'BUY_SELL') return 'Mua và bán';
        return translateDisplayText(value);
    }

    function strategyVersionReadyForAi(version) {
        const status = String(version.status || '').toLowerCase();
        return !!version.rulesJson && !['rejected', 'retired', 'archived'].includes(status);
    }

    function renderStrategyV2Manager() {
        const list = $('#strategyV2List');
        const detail = $('#strategyV2Detail');
        if (!list) return;

        const versions = state.strategyV2Versions || [];
        const summary = $('#strategyV2Summary');
        if (summary) {
            summary.innerHTML = [
                memoryRow('Tổng số version', number(versions.length)),
                memoryRow('Mới nhất', strategyV2Label(state.strategyV2Latest)),
                memoryRow('Production', strategyV2Label(state.strategyV2Production)),
            ].join('');
        }

        if (state.strategyV2Loading) {
            list.innerHTML = '<div class="notice">Đang tải phiên bản V2...</div>';
            detail.innerHTML = '<div class="notice">Đang tải dữ liệu SQLite.</div>';
            setStrategyV2ActionState(null);
            return;
        }

        if (state.strategyV2Error) {
            list.innerHTML = `<div class="notice">${escapeHtml(translateDisplayText(state.strategyV2Error))}</div>`;
            detail.innerHTML = '<div class="notice">Không đọc được dữ liệu V2.</div>';
            setStrategyV2ActionState(null);
            return;
        }

        if (!state.strategyV2Loaded) {
            list.innerHTML = '<div class="notice">Chưa tải dữ liệu V2.</div>';
            detail.innerHTML = '<div class="notice">Phiên bản V2 được lưu trong SQLite.</div>';
            renderStrategyV2Form(null);
            setStrategyV2ActionState(null);
            return;
        }

        if (!versions.length) {
            list.innerHTML = '<div class="notice">Chưa có phiên bản chiến lược V2.</div>';
            detail.innerHTML = '<div class="notice">Tạo version đầu tiên từ biểu mẫu.</div>';
            renderStrategyV2Form(null);
            setStrategyV2ActionState(null);
            return;
        }

        if (!state.selectedStrategyV2Id || !versions.some((version) => version.id === state.selectedStrategyV2Id)) {
            state.selectedStrategyV2Id = versions[0].id;
        }

        const selected = selectedStrategyV2Version();
        list.innerHTML = versions
            .map((version) => {
                const active = version.id === state.selectedStrategyV2Id ? ' is-active' : '';
                const badgeClass = strategyV2StatusClass(version.status);
                return `
                    <button class="profile-item strategy-v2-item${active}" type="button" data-strategy-v2-id="${escapeAttr(version.id)}">
                        <strong>${escapeHtml(strategyV2Label(version))}</strong>
                        <span>${escapeHtml(translateStatus(version.status))} | ${escapeHtml(version.created_by || version.createdBy || 'user')} | ${escapeHtml(version.parent_version_id || version.parentVersionId || 'Bản gốc')}</span>
                        <span class="status-badge ${escapeAttr(badgeClass)}">${escapeHtml(translateStatus(version.status))}</span>
                    </button>
                `;
            })
            .join('');

        $$('.strategy-v2-item').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedStrategyV2Id = button.dataset.strategyV2Id;
                renderStrategyV2Manager();
            });
        });

        renderStrategyV2Form(selected);
        renderStrategyV2Detail(selected);
        setStrategyV2ActionState(selected);
    }

    function renderStrategyV2Form(version) {
        const form = $('#strategyV2Form');
        if (!form) return;

        const parentSelect = $('#strategyV2Parent');
        const selectedId = version?.parent_version_id || version?.parentVersionId || '';
        parentSelect.innerHTML = '<option value="">Không có</option>' + (state.strategyV2Versions || [])
            .filter((item) => item.id !== version?.id)
            .map((item) => `<option value="${escapeAttr(item.id)}" ${item.id === selectedId ? 'selected' : ''}>${escapeHtml(strategyV2Label(item))}</option>`)
            .join('');

        $('#strategyV2Name').value = version?.name || '';
        $('#strategyV2Code').value = version?.version_code || version?.versionCode || '';
        $('#strategyV2Status').value = version?.status || 'Draft';
        $('#strategyV2CreatedBy').value = version?.created_by || version?.createdBy || 'user';
        $('#strategyV2Description').value = version?.description || version?.notes || '';
        $('#strategyV2RulesJson').value = jsonForTextarea(version?.rules_json || version?.rulesJson || {});
        $('#strategyV2SettingsJson').value = jsonForTextarea(version?.settings_json || version?.settingsJson || {});
        $('#strategyV2PerformanceJson').value = jsonForTextarea(version?.performance_json || version?.performanceJson || version?.backtestSummary || {});
    }

    function renderStrategyV2Detail(version) {
        const detail = $('#strategyV2Detail');
        if (!detail) return;
        if (!version) {
            detail.innerHTML = '<div class="notice">Chưa chọn phiên bản.</div>';
            return;
        }

        const rules = version.rules_json || version.rulesJson || {};
        const settings = version.settings_json || version.settingsJson || {};
        const performance = version.performance_json || version.performanceJson || version.backtestSummary || {};
        const children = (state.strategyV2Versions || []).filter((item) => (item.parent_version_id || item.parentVersionId || '') === version.id);
        const history = version.history || [];

        detail.innerHTML = `
            <div class="version-detail-head">
                <div>
                    <h3>${escapeHtml(strategyV2Label(version))}</h3>
                    <span>${escapeHtml(version.id)} | ${escapeHtml(translateStatus(version.status))}</span>
                </div>
                <div class="version-badges">
                    ${version.id === state.strategyV2Latest?.id ? '<span class="status-badge">Mới nhất</span>' : ''}
                    ${version.id === state.strategyV2Production?.id ? '<span class="status-badge production">Production</span>' : ''}
                    <span class="status-badge ${escapeAttr(strategyV2StatusClass(version.status))}">${escapeHtml(translateStatus(version.status))}</span>
                </div>
            </div>
            <div class="memory-summary">
                ${memoryRow('Mã version', version.version_code || version.versionCode || '')}
                ${memoryRow('Phiên bản cha', version.parent_version_id || version.parentVersionId || 'Không có')}
                ${memoryRow('Phiên bản con', children.length ? children.map(strategyV2Label).join(', ') : 'Không có')}
                ${memoryRow('Người tạo', version.created_by || version.createdBy || 'user')}
                ${settings.source_ai_job_id ? memoryRow('AI job nguồn', settings.source_ai_job_id) : ''}
                ${settings.source_experiment_id ? memoryRow('Thí nghiệm nguồn', settings.source_experiment_id) : ''}
                ${memoryRow('Tạo lúc', version.created_at || version.createdAt || '')}
                ${memoryRow('Cập nhật', version.updated_at || version.updatedAt || '')}
                ${memoryRow('Win rate', version.win_rate === null || version.win_rate === undefined ? 'Không có' : `${formatFixed(version.win_rate, 2)}%`)}
                ${memoryRow('Profit factor', version.profit_factor === null || version.profit_factor === undefined ? 'Không có' : formatFixed(version.profit_factor, 2))}
                ${memoryRow('Tổng lệnh', version.total_trades === null || version.total_trades === undefined ? 'Không có' : number(version.total_trades))}
            </div>
            <div class="strategy-v2-json-grid">
                <details open class="json-details">
                    <summary>Rules</summary>
                    <pre>${escapeHtml(JSON.stringify(rules, null, 2))}</pre>
                </details>
                <details open class="json-details">
                    <summary>Settings</summary>
                    <pre>${escapeHtml(JSON.stringify(settings, null, 2))}</pre>
                </details>
                <details open class="json-details">
                    <summary>Performance</summary>
                    <pre>${escapeHtml(JSON.stringify(performance, null, 2))}</pre>
                </details>
            </div>
            <details class="json-details">
                <summary>Lịch sử</summary>
                <pre>${escapeHtml(JSON.stringify(history, null, 2))}</pre>
            </details>
        `;
    }

    function setStrategyV2ActionState(version) {
        ['updateStrategyV2Btn', 'cloneStrategyV2Btn', 'runStrategyV2BacktestBtn', 'approveStrategyV2Btn', 'promoteStrategyV2Btn', 'archiveStrategyV2Btn'].forEach((id) => {
            const button = document.getElementById(id);
            if (button) button.disabled = !version;
        });
    }

    function strategyV2StatusClass(status) {
        const value = String(status || '').toLowerCase();
        if (value === 'production' || value === 'live') return 'production';
        if (value === 'archived' || value === 'retired') return 'archived';
        return '';
    }

    function strategyV2Label(version) {
        if (!version) return 'Không có';
        const code = version.version_code || version.versionCode || '';
        const name = displayStrategyVersionName(version.name || '');
        return code && name ? `${code} | ${name}` : name || code || version.id || 'Không có';
    }

    function jsonForTextarea(value) {
        return JSON.stringify(value && typeof value === 'object' ? value : {}, null, 2);
    }

    function renderPlaybook() {
        const standalone = $('#playbookContent');
        const embedded = $('#strategyPlaybookContent');
        const summary = state.strategySummary || {};
        const sections = summary.sections || [];
        if (!sections.length) {
            const emptyHtml = '<section class="panel"><span>Chưa có dữ liệu sổ chiến lược.</span></section>';
            if (standalone) standalone.innerHTML = emptyHtml;
            if (embedded) embedded.innerHTML = '<div class="notice">Chưa có dữ liệu tóm tắt phương pháp.</div>';
            return;
        }

        const cardsHtml = playbookCardsHtml(sections);
        if (standalone) {
            standalone.innerHTML = `
            <section class="playbook-head">
                <div>
                    <h2>${escapeHtml(translateDisplayText(summary.title || 'Sổ chiến lược'))}</h2>
                    <p>Cập nhật: ${escapeHtml(summary.updated_at || '')}</p>
                </div>
                <a class="button secondary" href="api.php?action=export_memory">Xuất kèm bộ nhớ AI</a>
            </section>
            <div class="playbook-grid">
                ${cardsHtml}
            </div>
            `;
        }

        if (embedded) {
            embedded.innerHTML = `
                <div class="strategy-playbook-actions">
                    <span>Cập nhật: ${escapeHtml(summary.updated_at || '')}</span>
                    <a class="button secondary" href="api.php?action=export_memory">Xuất kèm bộ nhớ AI</a>
                </div>
                <div class="playbook-grid">
                    ${cardsHtml}
                </div>
            `;
        }
    }

    function playbookCardsHtml(sections) {
        return sections
            .map((section) => {
                return `
                    <article class="playbook-card">
                        <h3>${escapeHtml(translateDisplayText(section.title || ''))}</h3>
                        <ul>
                            ${(section.items || []).map((item) => `<li>${escapeHtml(translateDisplayText(item))}</li>`).join('')}
                        </ul>
                    </article>
                `;
            })
            .join('');
    }

    function savedMt5Settings() {
        try {
            const parsed = JSON.parse(localStorage.getItem(MT5_SETTINGS_KEY) || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function readPaWorkspaceStore() {
        try {
            const parsed = JSON.parse(localStorage.getItem(PA_WORKSPACE_STORAGE_KEY) || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function paWorkspaceKey(symbol = state.paTrainingSymbol, timeframe = state.paTrainingTimeframe) {
        const safeSymbol = String(symbol || 'GLOBAL').toUpperCase().replace(/[^A-Z0-9]/g, '') || 'GLOBAL';
        return `${safeSymbol}_${String(timeframe || 'H4').toUpperCase()}`;
    }

    function savedPaWorkspace(symbol = state.paTrainingSymbol, timeframe = state.paTrainingTimeframe) {
        const key = paWorkspaceKey(symbol, timeframe);
        const candidates = [state.paTrainingWorkspace?.[key], readPaWorkspaceStore()[key]]
            .filter((item) => item && typeof item === 'object');
        // A stale browser copy can contain a reset/null cursor from an older
        // build. Keep a valid server cursor when either source still has one.
        const withReplay = candidates.filter((item) => item.replayIndex !== null && item.replayIndex !== undefined && Number.isFinite(Number(item.replayIndex)));
        const pool = withReplay.length ? withReplay : candidates;
        const item = pool.sort((a, b) => String(b.updatedAt || '').localeCompare(String(a.updatedAt || '')))[0];
        return item && typeof item === 'object' ? item : {};
    }

    function persistPaWorkspace(patch = {}) {
        try {
            const symbol = patch.symbol || state.paTrainingSymbol;
            const timeframe = patch.timeframe || state.paTrainingTimeframe || 'H4';
            const store = readPaWorkspaceStore();
            const key = paWorkspaceKey(symbol, timeframe);
            const previous = { ...(store[key] || {}), ...savedPaWorkspace(symbol, timeframe) };
            const hasReplayIndex = Object.prototype.hasOwnProperty.call(patch, 'replayIndex');
            const hasReplayEnabled = Object.prototype.hasOwnProperty.call(patch, 'replayEnabled');
            const requestedReplayIndex = hasReplayIndex ? patch.replayIndex : (previous.replayIndex ?? (state.chartView.replay.enabled ? state.chartView.replay.index : null));
            const keepValidReplay = requestedReplayIndex === null && previous.replayIndex !== null && previous.replayIndex !== undefined && Number.isFinite(Number(previous.replayIndex));
            const chartData = state.activeMarketData;
            const expectedChartTimeframe = String(patch.chartTimeframe || state.paTrainingChartTimeframe || chartData?.timeframe || '').toUpperCase();
            const chartMatches = chartData && sameSymbol(chartData.symbol, symbol)
                && String(chartData.timeframe || '').toUpperCase() === expectedChartTimeframe;
            const chartBars = chartMatches ? (chartData.bars || []) : [];
            const viewportIndex = chartBars.length
                ? clamp(chartBars.length - 1 - Math.round(Number(state.chartView.rightOffset) || 0), 0, chartBars.length - 1)
                : -1;
            const replayBar = chartMatches && state.chartView.replay.enabled
                ? chartData.bars?.[clamp(Math.round(Number(state.chartView.replay.index) || 0), 0, Math.max(0, (chartData.bars?.length || 1) - 1))]
                : null;
            store[key] = {
                ...previous,
                symbol,
                timeframe,
                selectedCaseId: patch.selectedCaseId ?? state.paTrainingSelectedCaseId ?? '',
                skippedCaseIds: Array.isArray(patch.skippedCaseIds) ? patch.skippedCaseIds.slice(0, 500) : state.paTrainingSkippedCaseIds.slice(0, 500),
                replayIndex: keepValidReplay ? previous.replayIndex : requestedReplayIndex,
                replayEnabled: keepValidReplay ? previous.replayEnabled !== false : (hasReplayEnabled ? patch.replayEnabled : (previous.replayEnabled ?? state.chartView.replay.enabled)),
                chartTimeframe: patch.chartTimeframe || (chartMatches ? chartData.timeframe : previous.chartTimeframe) || timeframe,
                barsOnScreen: chartMatches ? Math.round(Number(state.chartView.barsOnScreen) || 180) : (previous.barsOnScreen ?? null),
                rightOffset: chartMatches ? Math.round(Number(state.chartView.rightOffset) || 0) : (previous.rightOffset ?? 0),
                viewportRightTime: viewportIndex >= 0 ? barTimeValue(chartBars[viewportIndex]) : (previous.viewportRightTime ?? null),
                priceScale: chartMatches ? Number(state.chartView.priceScale) || 1 : (previous.priceScale ?? 1),
                priceOffset: chartMatches ? Number(state.chartView.priceOffset) || 0 : (previous.priceOffset ?? 0),
                replayTime: state.chartView.replay.enabled && state.chartView.replay.time !== null && Number.isFinite(Number(state.chartView.replay.time))
                    ? Number(state.chartView.replay.time)
                    : (replayBar ? barTimeValue(replayBar) : (previous.replayTime ?? null)),
                updatedAt: new Date().toISOString(),
            };
            localStorage.setItem(PA_WORKSPACE_STORAGE_KEY, JSON.stringify(store));
            if (paWorkspacePersistTimer) clearTimeout(paWorkspacePersistTimer);
            paWorkspacePersistTimer = setTimeout(() => {
                api('pa_training_workspace_state', { state: readPaWorkspaceStore() }).catch(() => {});
            }, 120);
        } catch (error) {
            // Workspace recovery is best effort when browser storage is unavailable.
        }
    }

    function restorePaWorkspaceSelection() {
        const store = { ...(state.paTrainingWorkspace || {}), ...readPaWorkspaceStore() };
        const entries = Object.values(store).filter((item) => item && typeof item === 'object' && item.symbol);
        const last = entries.sort((a, b) => String(b.updatedAt || '').localeCompare(String(a.updatedAt || '')))[0];
        if (!last) return null;
        state.paTrainingSymbol = String(last.symbol || state.paTrainingSymbol || '');
        state.paTrainingTimeframe = String(last.timeframe || state.paTrainingTimeframe || 'H4').toUpperCase();
        state.paTrainingChartTimeframe = String(last.chartTimeframe || state.paTrainingTimeframe || 'H4').toUpperCase();
        state.paTrainingSelectedCaseId = String(last.selectedCaseId || '');
        state.paTrainingSkippedCaseIds = Array.isArray(last.skippedCaseIds) ? last.skippedCaseIds.slice(0, 500) : [];
        return last;
    }

    function restorePaTrainingChartViewport(data = state.activeMarketData, savedOverride = null) {
        const bars = data?.bars || [];
        if (!bars.length) return false;
        const saved = savedOverride || savedPaWorkspace(data.symbol || state.paTrainingSymbol, state.paTrainingTimeframe);
        const savedTimeframe = String(saved.chartTimeframe || '').toUpperCase();
        if (savedTimeframe && savedTimeframe !== String(data.timeframe || '').toUpperCase()) return false;
        const hasViewport = (saved.barsOnScreen !== null && saved.barsOnScreen !== undefined && Number.isFinite(Number(saved.barsOnScreen)))
            || (saved.viewportRightTime !== null && saved.viewportRightTime !== undefined && Number.isFinite(Number(saved.viewportRightTime)))
            || (saved.rightOffset !== null && saved.rightOffset !== undefined && Number.isFinite(Number(saved.rightOffset)))
            || (saved.priceScale !== null && saved.priceScale !== undefined && Number.isFinite(Number(saved.priceScale)))
            || (saved.priceOffset !== null && saved.priceOffset !== undefined && Number.isFinite(Number(saved.priceOffset)));
        if (!hasViewport) return false;

        const span = clamp(Math.round(Number(saved.barsOnScreen) || state.chartView.barsOnScreen || 180), Math.min(12, bars.length), bars.length);
        const hasRightTime = saved.viewportRightTime !== null && saved.viewportRightTime !== undefined && Number.isFinite(Number(saved.viewportRightTime));
        const savedRightTime = hasRightTime ? Number(saved.viewportRightTime) : NaN;
        const savedRightOffset = Math.round(Number(saved.rightOffset) || 0);
        const restoreByTime = Number.isFinite(savedRightTime) && savedRightOffset >= 0;
        const rightIndex = restoreByTime
            ? chartIndexAtOrBeforeTime(bars, savedRightTime, bars.length - 1)
            : clamp(bars.length - 1 - savedRightOffset, 0, bars.length - 1);
        const limits = chartRightOffsetLimits(bars.length, span);
        const rightOffset = restoreByTime
            ? bars.length - 1 - rightIndex
            : savedRightOffset;
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(rightOffset, limits.min, limits.max);
        state.chartView.priceScale = clamp(Number(saved.priceScale) || 1, 0.2, 20);
        state.chartView.priceOffset = Number.isFinite(Number(saved.priceOffset)) ? Number(saved.priceOffset) : 0;
        drawMarketChart(data);
        return true;
    }

    function restorePaTrainingReplayProgress(data = state.activeMarketData, savedOverride = null) {
        const bars = data?.bars || [];
        if (!bars.length) return false;
        const saved = savedOverride || savedPaWorkspace(data.symbol || state.paTrainingSymbol, data.timeframe || state.paTrainingTimeframe);
        if (saved.replayIndex === null || saved.replayIndex === undefined || !Number.isFinite(Number(saved.replayIndex))) return false;
        const savedTimeframe = String(saved.chartTimeframe || saved.timeframe || '').toUpperCase();
        const currentTimeframe = String(data.timeframe || '').toUpperCase();
        if (savedTimeframe !== currentTimeframe && (saved.replayTime === null || saved.replayTime === undefined || !Number.isFinite(Number(saved.replayTime)))) return false;
        state.chartView.replay.enabled = saved.replayEnabled !== false;
        state.chartView.replay.index = savedTimeframe === currentTimeframe
            ? clamp(Math.round(Number(saved.replayIndex)), 0, bars.length - 1)
            : chartIndexAtOrBeforeTime(bars, Number(saved.replayTime), 0);
        const replayBar = bars[state.chartView.replay.index];
        state.chartView.replay.time = saved.replayTime !== null && Number.isFinite(Number(saved.replayTime))
            ? Number(saved.replayTime)
            : (replayBar ? barTimeValue(replayBar) + timeframeSeconds(data.timeframe) - 1 : null);
        centerChartOnIndex(state.chartView.replay.index);
        renderReplayControls(data);
        drawMarketChart(data);
        return true;
    }

    function saveMt5Settings(settings) {
        try {
            localStorage.setItem(
                MT5_SETTINGS_KEY,
                JSON.stringify({
                    terminal_path: settings.terminal_path || '',
                    symbols: settings.symbols || [],
                    timeframes: settings.timeframes || [],
                    bars: settings.bars || DEFAULT_MT5_BARS,
                })
            );
        } catch (error) {
            // localStorage can be blocked in some browser privacy modes.
        }
    }

    function loadChartDrawings() {
        try {
            const parsed = JSON.parse(localStorage.getItem(CHART_DRAWINGS_KEY) || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function persistChartDrawings() {
        try {
            localStorage.setItem(CHART_DRAWINGS_KEY, JSON.stringify(state.chartView.drawings || {}));
        } catch (error) {
            // Drawing persistence is best-effort only.
        }
    }

    function loadChartFavorites() {
        try {
            const parsed = JSON.parse(localStorage.getItem(CHART_FAVORITES_KEY) || '[]');
            return Array.isArray(parsed) && parsed.length ? parsed : DEFAULT_FAVORITE_TOOLS.slice();
        } catch (error) {
            return DEFAULT_FAVORITE_TOOLS.slice();
        }
    }

    function persistChartFavorites() {
        try {
            localStorage.setItem(CHART_FAVORITES_KEY, JSON.stringify(state.chartView.favoriteTools || []));
        } catch (error) {
            // Favorite toolbar persistence is best-effort only.
        }
    }

    function loadChartSettings() {
        try {
            const parsed = JSON.parse(localStorage.getItem(CHART_SETTINGS_KEY) || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function persistChartSettings() {
        try {
            localStorage.setItem(CHART_SETTINGS_KEY, JSON.stringify({
                chartType: state.chartView.chartType,
                scaleMode: state.chartView.scaleMode,
                timezone: state.chartView.timezone,
                magnetMode: state.chartView.magnetMode,
                magnetModeExplicit: state.chartView.magnetModeExplicit,
                stayInDrawingMode: state.chartView.stayInDrawingMode,
                favoritesVisible: state.chartView.favoritesVisible,
                layoutSlots: state.chartView.layoutSlots,
                indicatorTemplate: state.chartView.indicatorTemplate,
                indicatorColors: state.chartView.indicatorColors,
                indicators: state.chartView.indicators,
                drawingStyle: state.chartView.drawingStyle,
                floatingToolbarPosition: state.chartView.floatingToolbarPosition,
            }));
        } catch (error) {
            // Settings persistence is best-effort only.
        }
    }

    function loadChartLayouts() {
        try {
            const parsed = JSON.parse(localStorage.getItem(CHART_LAYOUTS_KEY) || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function persistChartLayouts(layouts) {
        try {
            localStorage.setItem(CHART_LAYOUTS_KEY, JSON.stringify(layouts || {}));
        } catch (error) {
            // Layout persistence is best-effort only.
        }
    }

    function applySavedChartSettings() {
        const settings = loadChartSettings();
        if (!settings || typeof settings !== 'object') return;
        if (settings.scaleMode) state.chartView.scaleMode = settings.scaleMode;
        if (settings.timezone) state.chartView.timezone = settings.timezone;
        if (['off', 'weak', 'strong'].includes(settings.magnetMode)) {
            state.chartView.magnetMode = settings.magnetMode === 'strong' && settings.magnetModeExplicit !== true
                ? 'off'
                : settings.magnetMode;
        }
        if (typeof settings.stayInDrawingMode === 'boolean') state.chartView.stayInDrawingMode = settings.stayInDrawingMode;
        if (Number.isFinite(Number(settings.layoutSlots))) state.chartView.layoutSlots = clamp(Number(settings.layoutSlots), 1, 4);
        if (settings.indicatorTemplate) state.chartView.indicatorTemplate = settings.indicatorTemplate;
        if (settings.indicatorColors && typeof settings.indicatorColors === 'object') {
            state.chartView.indicatorColors = { ...state.chartView.indicatorColors, ...settings.indicatorColors };
        }
        if (settings.indicators && typeof settings.indicators === 'object') {
            Object.assign(state.chartView.indicators, settings.indicators);
        }
        if (settings.drawingStyle && typeof settings.drawingStyle === 'object') {
            state.chartView.drawingStyle = { ...state.chartView.drawingStyle, ...settings.drawingStyle };
        }
        if (settings.floatingToolbarPosition && typeof settings.floatingToolbarPosition === 'object') {
            const left = Number(settings.floatingToolbarPosition.left);
            const top = Number(settings.floatingToolbarPosition.top);
            if (Number.isFinite(left) && Number.isFinite(top)) {
                state.chartView.floatingToolbarPosition = { left, top };
            }
        }
        state.chartView.chartType = 'candles';
        state.chartView.favoritesVisible = false;
        state.chartView.snapToOhlc = state.chartView.magnetMode !== 'off';
    }

    function chartHistoryFor(data = state.activeMarketData) {
        const key = chartDrawingKey(data);
        if (!state.chartView.drawingHistory[key]) {
            state.chartView.drawingHistory[key] = { undo: [], redo: [] };
        }
        return state.chartView.drawingHistory[key];
    }

    function snapshotChartDrawings(data = state.activeMarketData) {
        return JSON.stringify(getChartDrawings(data));
    }

    function pushDrawingHistory(data = state.activeMarketData) {
        if (!data) return;
        const history = chartHistoryFor(data);
        const snapshot = snapshotChartDrawings(data);
        if (history.undo[history.undo.length - 1] !== snapshot) {
            history.undo.push(snapshot);
            if (history.undo.length > CHART_HISTORY_LIMIT) history.undo.shift();
        }
        history.redo = [];
    }

    function restoreDrawingSnapshot(snapshot, data = state.activeMarketData) {
        if (!data) return false;
        try {
            const parsed = JSON.parse(snapshot || '[]');
            state.chartView.drawings[chartDrawingKey(data)] = Array.isArray(parsed) ? parsed : [];
            state.chartView.drawings[chartDrawingKey(data)].forEach((drawing) => getDrawingId(drawing));
            state.chartView.selectedDrawingIds = [];
            state.chartView.draftDrawing = null;
            state.chartView.selectionBox = null;
            persistChartDrawings();
            return true;
        } catch (error) {
            return false;
        }
    }

    function undoChartDrawing() {
        if (!state.activeMarketData) return;
        const history = chartHistoryFor();
        const snapshot = history.undo.pop();
        if (!snapshot) return;
        history.redo.push(snapshotChartDrawings());
        if (restoreDrawingSnapshot(snapshot)) {
            drawMarketChart(state.activeMarketData);
        }
    }

    function redoChartDrawing() {
        if (!state.activeMarketData) return;
        const history = chartHistoryFor();
        const snapshot = history.redo.pop();
        if (!snapshot) return;
        history.undo.push(snapshotChartDrawings());
        if (restoreDrawingSnapshot(snapshot)) {
            drawMarketChart(state.activeMarketData);
        }
    }

    function hydrateMt5Form() {
        const form = $('#mt5Form');
        if (!form || form.dataset.hydrated === '1') return;

        const settings = savedMt5Settings();
        const savedSymbols = Array.isArray(settings.symbols) ? settings.symbols : [];
        const symbols = savedSymbols.length ? savedSymbols : state.strategy?.symbols || [];
        let bars = Number.isFinite(Number(settings.bars)) ? clamp(Math.round(Number(settings.bars)), 50, MAX_MT5_BARS) : DEFAULT_MT5_BARS;
        if (bars === LEGACY_DEFAULT_MT5_BARS) {
            bars = DEFAULT_MT5_BARS;
        }
        const timeframes = Array.isArray(settings.timeframes) ? settings.timeframes.map((item) => String(item).toUpperCase()) : [];

        $('#mt5Bars').max = String(MAX_MT5_BARS);
        $('#mt5Bars').value = String(bars);
        $('#mt5TerminalPath').value = typeof settings.terminal_path === 'string' ? settings.terminal_path : '';
        if (symbols.length) {
            $('#mt5Symbols').value = symbols.join(', ');
        }
        if (timeframes.length) {
            $$('[data-mt5-timeframe]').forEach((input) => {
                input.checked = timeframes.includes(input.dataset.mt5Timeframe);
            });
        }
        form.dataset.hydrated = '1';
        updateMt5BarsHint();
    }

    function readMt5FetchForm() {
        const timeframes = $$('[data-mt5-timeframe]')
            .filter((input) => input.checked)
            .map((input) => input.dataset.mt5Timeframe);
        const symbols = $('#mt5Symbols').value
            .split(',')
            .map((v) => v.trim().toUpperCase())
            .filter(Boolean);
        const barsInput = $('#mt5Bars').value.trim();
        const bars = barsInput ? clamp(intNum(barsInput), 50, MAX_MT5_BARS) : DEFAULT_MT5_BARS;
        const barsByTimeframe = buildMt5BarsByTimeframe(timeframes, bars);

        return {
            terminal_path: $('#mt5TerminalPath').value.trim(),
            symbols,
            timeframes,
            bars,
            bars_by_timeframe: barsByTimeframe,
        };
    }

    function buildMt5BarsByTimeframe(timeframes, baseM15Bars) {
        return timeframes.reduce((result, timeframe) => {
            result[timeframe] = mt5BarsForTimeframe(timeframe, baseM15Bars);
            return result;
        }, {});
    }

    function mt5BarsForTimeframe(timeframe, baseM15Bars) {
        const minutes = MT5_TIMEFRAME_MINUTES[timeframe] || MT5_BASE_MINUTES;
        return clamp(Math.ceil((baseM15Bars * MT5_BASE_MINUTES) / minutes), 50, MAX_MT5_FETCH_BARS);
    }

    function mt5BarsPlanText(barsByTimeframe) {
        return Object.entries(barsByTimeframe || {})
            .map(([timeframe, bars]) => `${timeframe}: ${number(bars)}`)
            .join(' | ');
    }

    function updateMt5BarsHint() {
        const hint = $('#mt5BarsHint');
        if (!hint) return;
        const payload = readMt5FetchForm();
        const plan = mt5BarsPlanText(payload.bars_by_timeframe);
        hint.textContent = plan
            ? `M15 là mốc chính. App sẽ yêu cầu: ${plan}. MT5 trả dữ liệu mới thì sẽ ghi đè file cũ.`
            : 'M15 là mốc chính, tối đa 100.000 nến. Chọn khung thời gian để xem số nến sẽ yêu cầu. Dữ liệu MT5 mới sẽ ghi đè file cũ.';
    }

    function persistMt5Form() {
        saveMt5Settings(readMt5FetchForm());
    }

    function renderMt5() {
        hydrateMt5Form();
        renderMarketFiles();
        renderChartSymbolSelect();
        renderSymbolWatchlist();
        updateChartTimeframeButtons();
        renderChartControlState();
        if (!state.activeMarketData && state.marketFiles.length) {
            const savedFile = state.paTrainingSymbol
                ? findBestMarketFileForSymbol(state.paTrainingSymbol, state.paTrainingChartTimeframe || state.paTrainingTimeframe)
                : null;
            loadMarketData(savedFile?.file || state.marketFiles[0].file).then(() => {
                if (savedFile && state.activeTab === 'pa-training') {
                    restorePaTrainingChartViewport(state.activeMarketData, savedPaWorkspace(state.paTrainingSymbol, state.paTrainingTimeframe));
                }
            }).catch((e) => showToast(e.message));
            return;
        }
        if (state.activeMarketData) {
            drawMarketChart(state.activeMarketData);
        }
    }

    function renderMarketFiles() {
        const files = state.marketFiles || [];
        $('#marketFileCount').textContent = `${files.length}`;
        if (!files.length) {
            $('#marketFileList').innerHTML = '<div class="review-item"><span>Chưa có dữ liệu MT5 nào được lưu.</span></div>';
            return;
        }

        $('#marketFileList').innerHTML = files
            .map((item) => {
                const active = item.file === state.activeMarketFile ? ' is-active' : '';
                return `
                    <button class="market-file-item${active}" type="button" data-market-file="${escapeAttr(item.file)}">
                        <strong>${escapeHtml(item.symbol)} ${escapeHtml(item.timeframe)}</strong>
                        <span>${number(item.bars)} nến | ${escapeHtml(item.fetched_at || '')}</span>
                    </button>
                `;
            })
            .join('');

        $$('[data-market-file]').forEach((button) => {
            button.addEventListener('click', () => loadMarketData(button.dataset.marketFile).catch((e) => showToast(e.message)));
        });
        renderSymbolWatchlist();
    }

    function chartSymbols() {
        return [...new Set([...(state.strategy.symbols || []), ...(state.marketFiles || []).map((file) => file.symbol).filter(Boolean)])]
            .filter(Boolean)
            .sort();
    }

    function renderSymbolWatchlist() {
        const symbols = chartSymbols();
        $('#watchlistCount').textContent = `${symbols.length}`;
        if (!symbols.length) {
            $('#symbolWatchlist').innerHTML = '<div class="review-item"><span>Chưa có danh sách cặp tiền.</span></div>';
            return;
        }

        $('#symbolWatchlist').innerHTML = symbols
            .map((symbol) => {
                const file = findMarketFile(symbol, state.chartView.timeframe);
                const active = state.activeMarketData?.symbol === symbol && state.activeMarketData?.timeframe === state.chartView.timeframe;
                return `
                    <button class="symbol-row ${active ? 'is-active' : ''} ${file ? '' : 'no-data'}" type="button" data-watch-symbol="${escapeAttr(symbol)}">
                        <strong>${escapeHtml(symbol)}</strong>
                        <span class="data-state" title="${file ? 'Có dữ liệu' : 'Chưa có dữ liệu'}"></span>
                    </button>
                `;
            })
            .join('');

        $$('[data-watch-symbol]').forEach((button) => {
            button.addEventListener('click', () => openWatchSymbol(button.dataset.watchSymbol).catch((e) => showToast(e.message)));
        });
    }

    function renderChartSymbolSelect() {
        const select = $('#chartSymbolSelect');
        if (!select) return;
        const symbols = chartSymbols();
        select.innerHTML = symbols.map((symbol) => `<option value="${escapeAttr(symbol)}">${escapeHtml(symbol)}</option>`).join('');
        if (state.activeMarketData?.symbol) select.value = state.activeMarketData.symbol;
    }

    function updateChartTimeframeButtons() {
        $$('[data-chart-timeframe]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.chartTimeframe === state.chartView.timeframe);
        });
        const moreMenu = $('#chartTimeframeMore');
        const moreToggle = $('#chartTimeframeMoreToggle');
        const hiddenActive = !!$('.chart-timeframe-menu [data-chart-timeframe].is-active');
        if (moreMenu) moreMenu.classList.toggle('has-active-timeframe', hiddenActive);
        if (moreToggle) moreToggle.classList.toggle('is-active', hiddenActive);
        updateChartSelectionJumpButtons();
    }

    function updateChartSelectionJumpButtons() {
        const selection = state.chartView.timeframeSelection;
        const usable = !!selection && !!state.activeMarketData && selection.symbol === state.activeMarketData.symbol;
        ['chartSelectionToM15Btn', 'chartSelectionToH1Btn'].forEach((id) => {
            const button = $(`#${id}`);
            if (button) button.disabled = !usable;
        });
    }

    async function jumpToSelectedChartRange(timeframe) {
        const selection = state.chartView.timeframeSelection;
        const symbol = state.activeMarketData?.symbol;
        if (!selection || !symbol || selection.symbol !== symbol) {
            showToast('Hãy chọn vùng trên biểu đồ trước. Dùng công cụ Chọn rồi kéo qua vùng cần xem.');
            return;
        }
        const file = findMarketFile(symbol, timeframe);
        if (!file) {
            showToast(`${symbol} ${timeframe} chưa có dữ liệu.`);
            return;
        }
        await loadMarketData(file.file);
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        const first = chartIndexForBarsTime(bars, selection.startTime, 0);
        const last = chartIndexForBarsTime(bars, selection.endTime, first);
        const low = Math.min(first, last);
        const high = Math.max(first, last);
        const selectedSpan = Math.max(1, high - low + 1);
        const span = clamp(Math.max(12, Math.ceil(selectedSpan * 1.45)), Math.min(12, bars.length), bars.length);
        const center = (low + high) / 2;
        const left = clamp(Math.round(center - span / 2), 0, Math.max(0, bars.length - span));
        const offsetLimits = chartRightOffsetLimits(bars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(bars.length - left - span, offsetLimits.min, offsetLimits.max);
        drawMarketChart(state.activeMarketData);
        showToast(`Đã chuyển đến vùng đã chọn trên ${symbol} ${timeframe}`);
    }

    function positionChartTimeframeMenu() {
        const wrapper = $('#chartTimeframeMore');
        const toggle = $('#chartTimeframeMoreToggle');
        const menu = $('#chartTimeframeMenu');
        if (!wrapper || !toggle || !menu || !wrapper.classList.contains('is-open')) return;
        const rect = toggle.getBoundingClientRect();
        const menuWidth = Math.max(280, menu.offsetWidth || 280);
        const left = Math.min(Math.max(8, rect.left), Math.max(8, window.innerWidth - menuWidth - 8));
        const top = Math.min(rect.bottom + 6, Math.max(8, window.innerHeight - 54));
        menu.style.setProperty('--chart-timeframe-menu-left', `${left}px`);
        menu.style.setProperty('--chart-timeframe-menu-top', `${top}px`);
    }

    function setChartTimeframeMenuOpen(open) {
        const wrapper = $('#chartTimeframeMore');
        const toggle = $('#chartTimeframeMoreToggle');
        const menu = $('#chartTimeframeMenu');
        if (!wrapper || !toggle || !menu) return;
        wrapper.classList.toggle('is-open', !!open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.hidden = !open;
        if (open) requestAnimationFrame(positionChartTimeframeMenu);
    }

    function findMarketFile(symbol, timeframe) {
        const wantedSymbol = normalizeSymbolKey(symbol);
        const wantedTimeframe = String(timeframe || '').toUpperCase();
        return (state.marketFiles || []).find((item) => normalizeSymbolKey(item.symbol) === wantedSymbol && String(item.timeframe || '').toUpperCase() === wantedTimeframe) || null;
    }

    function findBestMarketFileForSymbol(symbol, preferredTimeframe = 'H4') {
        const wantedSymbol = normalizeSymbolKey(symbol);
        if (!wantedSymbol) return null;
        const candidates = [
            preferredTimeframe,
            state.paTrainingChartTimeframe,
            state.chartView.timeframe,
            'H4',
            'M15',
            'H1',
            'M30',
            'M5',
            'M1',
            'D1',
        ]
            .map((value) => String(value || '').toUpperCase())
            .filter((value, index, list) => value && list.indexOf(value) === index);
        for (const timeframe of candidates) {
            const file = findMarketFile(wantedSymbol, timeframe);
            if (file) return file;
        }
        return (state.marketFiles || []).find((item) => normalizeSymbolKey(item.symbol) === wantedSymbol) || null;
    }

    async function openWatchSymbol(symbol) {
        const file = findMarketFile(symbol, state.chartView.timeframe);
        if (!file) {
            showToast(`${symbol} ${state.chartView.timeframe} chưa có dữ liệu. Bấm "Lấy dữ liệu từ MT5" trước.`);
            return;
        }
        await loadMarketData(file.file);
    }

    async function addCompareSymbol() {
        const input = $('#chartCompareInput');
        const symbol = String(input?.value || '').trim().toUpperCase();
        if (!symbol) return;
        const file = findMarketFile(symbol, state.chartView.timeframe);
        if (!file) {
            showToast(`${symbol} ${state.chartView.timeframe} chưa có dữ liệu so sánh`);
            return;
        }
        const data = await api('market_data', { file: file.file });
        state.chartView.compareData[symbol] = data.market_data;
        if (!state.chartView.compareSymbols.includes(symbol)) {
            state.chartView.compareSymbols.push(symbol);
        }
        if (input) input.value = '';
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function clearCompareSymbols() {
        state.chartView.compareSymbols = [];
        state.chartView.compareData = {};
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    async function mt5Status() {
        setMt5Status('Đang kiểm tra kết nối MT5...');
        const data = await api('mt5_status', { terminal_path: $('#mt5TerminalPath').value.trim() });
        const mt5 = data.mt5 || {};
        if (!mt5.ok) {
            setMt5Status(mt5ErrorHtml(mt5));
            return;
        }
        const account = mt5.account || {};
        const terminal = mt5.terminal || {};
        setMt5Status(`
            <div class="status-ok">
                <strong>Đã kết nối MT5</strong>
                <span>Tài khoản: ${escapeHtml(account.login || '')} | Máy chủ: ${escapeHtml(account.server || '')} | Số dư: ${money(account.balance)}</span>
                <span>Phần mềm MT5: ${escapeHtml(terminal.name || '')} | Bản dựng: ${escapeHtml((mt5.version || []).join('.'))}</span>
            </div>
        `);
    }

    async function mt5Fetch() {
        const payload = readMt5FetchForm();
        saveMt5Settings(payload);
        const button = $('#mt5FetchBtn');
        const startedAt = Date.now();
        let data;
        button.disabled = true;
        try {
            setMt5Status('<div class="mt5-progress"><strong>Đang khởi chạy lượt lấy dữ liệu MT5…</strong><span>Yêu cầu chạy nền; bạn có thể theo dõi tiến độ tại đây.</span></div>');
            const started = await api('mt5_fetch', payload);
            while (true) {
                await new Promise((resolve) => window.setTimeout(resolve, 1800));
                const status = await api('mt5_fetch_status', { job_id: started.job_id });
                const progress = status.progress || {};
                if (status.complete || progress.status === 'complete') {
                    data = status;
                    break;
                }
                const done = Number(progress.completed || 0);
                const total = Number(progress.total || payload.symbols.length * payload.timeframes.length || 0);
                const elapsed = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
                const remaining = done > 0 && total > done ? Math.ceil((elapsed / done) * (total - done)) : null;
                const current = progress.current_symbol && progress.current_timeframe
                    ? `Đang lấy ${escapeHtml(progress.current_symbol)} ${escapeHtml(progress.current_timeframe)}${progress.current_chunk ? ` · lô ${number(progress.current_chunk)}/${number(progress.chunk_total)}` : ''}`
                    : escapeHtml(progress.message || 'Đang kết nối MT5 và chuẩn bị dữ liệu');
                setMt5Status(`
                    <div class="mt5-progress">
                        <strong>MT5 đang chạy · ${number(done)} / ${number(total)} cặp-khung hoàn tất</strong>
                        <progress max="${Math.max(1, total)}" value="${Math.min(done, total)}"></progress>
                        <span>${current}</span>
                        <span>Đã chạy ${mt5DurationLabel(elapsed)}${remaining !== null ? ` · còn khoảng ${mt5DurationLabel(remaining)}` : ''}. Dữ liệu được ghi dần vào file.</span>
                    </div>
                `);
            }
        } catch (error) {
            setMt5Status(`<div class="status-error"><strong>Lượt lấy dữ liệu bị lỗi</strong><span>${escapeHtml(error.message || String(error))}</span></div>`);
            throw error;
        } finally {
            button.disabled = false;
        }
        const mt5 = data.mt5 || {};
        if (!data.ok) {
            setMt5Status(mt5ErrorHtml(mt5));
            return;
        }
        state.marketFiles = data.market_files || [];
        renderMarketFiles();
        const written = data.written || [];
        const keptExisting = written.filter((item) => item.kept_existing);
        const shortFetched = written.filter((item) => Number(item.fetched_bars || item.bars || 0) < requestedMt5Bars(item, payload));
        const requestPlan = mt5BarsPlanText(payload.bars_by_timeframe);
        setMt5Status(`
            <div class="status-ok">
                <strong>Đã cập nhật ${number(written.length)} tệp dữ liệu</strong>
                ${requestPlan ? `<span>Yêu cầu theo M15: ${escapeHtml(requestPlan)}</span>` : ''}
                <span>${written.map((item) => {
                    const fetchedBars = Number(item.fetched_bars ?? item.bars ?? 0);
                    const kept = Boolean(item.kept_existing);
                    return `${item.symbol} ${item.timeframe}: MT5 trả ${number(fetchedBars)} nến${kept ? `, đang lưu ${number(item.bars)}` : ''}`;
                }).join(' | ')}</span>
                ${
                    keptExisting.length
                        ? `<span>Đã giữ ${number(keptExisting.length)} tệp cũ vì dữ liệu đang lưu dài hơn dữ liệu MT5 vừa trả.</span>`
                        : ''
                }
                ${
                    shortFetched.length
                        ? `<span>Cảnh báo: có ${number(shortFetched.length)} tệp MT5 trả ít hơn số nến đã yêu cầu. Nếu muốn đủ hơn, hãy tăng “Số nến tối đa trên biểu đồ” trong MT5, khởi động lại MT5, tải thêm lịch sử rồi lấy dữ liệu lại.</span>`
                        : ''
                }
                ${(mt5.errors || []).length ? `<span>Lỗi: ${escapeHtml(JSON.stringify(mt5.errors))}</span>` : ''}
            </div>
        `);
        if (written[0]) {
            await loadMarketData(written[0].file);
        }
    }

    function mt5DurationLabel(seconds) {
        const value = Math.max(0, Math.floor(Number(seconds) || 0));
        const minutes = Math.floor(value / 60);
        const rest = value % 60;
        return minutes ? `${minutes} phút ${rest} giây` : `${rest} giây`;
    }

    function requestedMt5Bars(item, payload) {
        return Number(payload.bars_by_timeframe?.[item.timeframe] || payload.bars || 0);
    }

    async function refreshMarketFiles() {
        const data = await api('market_files', {});
        state.marketFiles = data.market_files || [];
        renderMarketFiles();
        showToast('Đã tải lại danh sách dữ liệu');
    }

    async function loadMarketData(file, options = {}) {
        const data = options.marketData
            ? { market_data: options.marketData }
            : await api('market_data', { file });
        state.activeMarketFile = file;
        state.activeMarketData = data.market_data;
        state.chartView.timeframe = state.activeMarketData?.timeframe || state.chartView.timeframe;
        state.chartView.selectedTradeId = null;
        state.chartView.crosshairPoint = null;
        state.chartView.analyticsCache = {};
        stopChartReplay(true);
        resetChartView(false);
        renderMarketFiles();
        renderChartSymbolSelect();
        renderSymbolWatchlist();
        updateChartTimeframeButtons();
        renderChartControlState();
        drawMarketChart(state.activeMarketData);
    }

    function setMt5Status(html) {
        $('#mt5Status').innerHTML = html;
    }

    function mt5ErrorHtml(mt5) {
        return `
            <div class="status-error">
                <strong>${escapeHtml(mt5.error || 'Không kết nối được MT5')}</strong>
                ${mt5.detail ? `<span>${escapeHtml(mt5.detail)}</span>` : ''}
                ${mt5.install_hint ? `<span>${escapeHtml(mt5.install_hint)}</span>` : ''}
                ${mt5.mt5_error ? `<span>Lỗi MT5: ${escapeHtml(JSON.stringify(mt5.mt5_error))}</span>` : ''}
            </div>
        `;
    }

    function readStrategyForm() {
        const s = structuredClone(state.strategy || {});
        s.name = $('#strat_name').value.trim();
        s.symbols = $('#strat_symbols').value
            .split(',')
            .map((v) => v.trim().toUpperCase())
            .filter(Boolean);
        s.description = $('#strat_notes').value.trim();
        s.trend = s.trend || {};
        s.trend.min_confidence = num($('#strat_min_confidence').value);
        s.risk_reward = s.risk_reward || {};
        s.risk_reward.min_rr = num($('#strat_min_rr').value);
        s.zones = s.zones || {};
        s.zones.h4_tp_candidates = intNum($('#strat_h4_tp_candidates').value);
        s.management = s.management || {};
        s.management.be_enabled = $('#strat_be_enabled').checked;
        s.management.be_trigger_r_min = num($('#strat_be_trigger_r_min').value);
        s.management.be_trigger_r_max = num($('#strat_be_trigger_r_max').value);
        s.management.partial_close = !$('#strat_no_partial_close').checked;
        s.management.close_size_pct = num($('#strat_close_size_pct').value);
        s.management.exit_on_m15_opposite_structure = $('#strat_exit_on_m15_opposite_structure').checked;
        s.management.exit_on_m15_sideway_break_against_trade = $('#strat_exit_on_m15_sideway_break_against_trade').checked;
        s.management.exit_at_h4_tp_zone_on_m15_reversal = $('#strat_exit_at_h4_tp_zone_on_m15_reversal').checked;
        s.management.exit_retest_level = $('#strat_exit_retest_level').value.trim() || 'broken_m15_key_level';
        s.filters = s.filters || {};
        s.filters.block_red_news_minutes_before = intNum($('#strat_news_before').value);
        s.filters.block_red_news_minutes_after = intNum($('#strat_news_after').value);
        return s;
    }

    function normalizePaTrainingMemory(memory) {
        return {
            zone_reviews: Array.isArray(memory?.zone_reviews) ? memory.zone_reviews : [],
            setup_reviews: Array.isArray(memory?.setup_reviews) ? memory.setup_reviews : [],
            lessons: Array.isArray(memory?.lessons) ? memory.lessons : [],
        };
    }

    function paTrainingSymbols() {
        const h4 = new Set();
        const m15 = new Set();
        (state.marketFiles || []).forEach((file) => {
            if (!file?.symbol || !file?.timeframe) return;
            if (file.timeframe === 'H4') h4.add(file.symbol);
            if (file.timeframe === 'M15') m15.add(file.symbol);
        });
        const both = [...h4].filter((symbol) => m15.has(symbol)).sort();
        if (both.length) return both;
        return [...new Set((state.marketFiles || []).map((file) => file.symbol).filter(Boolean))].sort();
    }

    function normalizeSymbolKey(symbol) {
        return String(symbol || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    function sameSymbol(a, b) {
        return normalizeSymbolKey(a) === normalizeSymbolKey(b);
    }

    function matchPaTrainingSymbol(value, symbols = paTrainingSymbols()) {
        const query = normalizeSymbolKey(value);
        if (!query) return '';
        return symbols.find((symbol) => sameSymbol(symbol, query))
            || symbols.find((symbol) => normalizeSymbolKey(symbol).startsWith(query))
            || symbols.find((symbol) => normalizeSymbolKey(symbol).includes(query))
            || query;
    }

    function updatePaTrainingSymbolControl(symbols, options = {}) {
        const symbol = state.paTrainingSymbol || '';
        const symbolSelect = $('#paTrainingSymbolSelect');
        const symbolSearch = $('#paTrainingSymbolSearch');
        const symbolList = $('#paTrainingSymbolList');
        if (symbolSelect) {
            symbolSelect.innerHTML = symbols.length
                ? symbols.map((item) => `<option value="${escapeAttr(item)}">${escapeHtml(item)}</option>`).join('')
                : '<option value="">Chưa có dữ liệu</option>';
            symbolSelect.value = symbol;
        }
        if (symbolList) {
            symbolList.innerHTML = symbols.map((item) => `<option value="${escapeAttr(item)}"></option>`).join('');
        }
        if (symbolSearch && (options.force === true || document.activeElement !== symbolSearch)) {
            symbolSearch.value = symbol;
        }
    }

    function changePaTrainingSymbol(value) {
        const symbols = paTrainingSymbols();
        const next = matchPaTrainingSymbol(value, symbols);
        if (!next) return;
        const existing = symbols.find((symbol) => sameSymbol(symbol, next));
        if (symbols.length && !existing) {
            showToast(`${next} chưa có dữ liệu H4/M15. Tải dữ liệu trước rồi chọn lại.`);
            updatePaTrainingSymbolControl(symbols, { force: true });
            return;
        }
        state.paTrainingSymbol = existing || next;
        state.paTrainingZones = [];
        state.paTrainingTradeCases = [];
        state.paTrainingSelectedCaseId = '';
        state.paTrainingSkippedCaseIds = [];
        state.paTrainingQueueLoaded = false;
        state.paTrainingSelectedZoneId = '';
        state.paTrainingChartData = null;
        state.paTrainingChartError = '';
        updatePaTrainingSymbolControl(symbols, { force: true });
        renderPaTraining();
        openPaTrainingSymbolChart('H4').catch((e) => showToast(e.message));
    }

    function renderPaTraining() {
        const panel = $('#tab-pa-training');
        if (!panel) return;
        const symbols = paTrainingSymbols();
        if (!state.paTrainingSymbol && symbols.length) {
            state.paTrainingSymbol = symbols[0];
        }
        updatePaTrainingSymbolControl(symbols);
        const timeframeSelect = $('#paTrainingTimeframeSelect');
        if (timeframeSelect) timeframeSelect.value = state.paTrainingTimeframe || 'H4';
        const filterSelect = $('#paTrainingZoneFilter');
        if (filterSelect) filterSelect.value = state.paTrainingZoneFilter || 'active';

                state.paTrainingMode = 'cases';
        $('#paTrainingZonesBtn')?.classList.add('is-active');
        const zonesView = $('#paTrainingZonesView');
        const setupsView = $('#paTrainingSetupsView');
        if (zonesView) zonesView.hidden = false;
        if (setupsView) setupsView.hidden = true;

        renderPaTrainingStats();
        renderPaTrainingZones();
        renderPaTrainingCoachZoneSummary();
        renderPaTrainingChat();
        renderPaTrainingChart();
        renderPaTrainingSetupGuide();
        renderPaTrainingRecentFeedback();
        syncPaWorkspaceUi();
    }

    function renderPaTrainingStats() {
        const target = $('#paTrainingMemoryStats');
        if (!target) return;
        const memory = normalizePaTrainingMemory(state.paTrainingMemory);
        const zones = memory.zone_reviews;
        const setups = memory.setup_reviews;
        const cases = state.paTrainingTradeCases || [];
        const manualZones = (state.paTrainingZones || []).filter((zone) => zone.manual || zone.source === 'manual').length;
        const countBy = (decision) => zones.filter((item) => item.decision === decision).length;
        target.innerHTML = [
            paTrainingStatCard('Ca chờ dạy', number(cases.length), 'Lệnh M15 đã vào và chạm SL/TP từ vùng H4'),
            paTrainingStatCard('Ca đã chấm', number(setups.length), 'Vùng + setup + entry/SL/TP đã được m phản hồi'),
            paTrainingStatCard('Vùng đã chấm', number(zones.length), 'Mẫu vùng H4 bạn đã dạy'),
            paTrainingStatCard('Chọn để trade', number(countBy('trade_zone')), 'AI nên ưu tiên vùng kiểu này'),
            paTrainingStatCard('Bỏ qua', number(countBy('skip_zone')), 'Vùng không hợp cách nhìn của bạn'),
            paTrainingStatCard('Vùng tự thêm', number(manualZones), 'Vùng m tự chọn hoặc vẽ trên chart'),
            paTrainingStatCard('Bài học PA', number(memory.lessons.length), 'Rule/kinh nghiệm AI sẽ dùng lại'),
        ].join('');
    }
    function paTrainingStatCard(label, value, sub) {
        return `<div class="pa-training-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong><small>${escapeHtml(sub)}</small></div>`;
    }

    function renderPaTrainingZones() {
        const list = $('#paTrainingZoneList');
        const detail = $('#paTrainingZoneDetail');
        if (!list) return;
        const cases = paTrainingFilteredCases();
        const zones = paTrainingFilteredZones();
        const hasCaseQueue = (state.paTrainingTradeCases || []).length > 0;
        const countLabel = hasCaseQueue ? `${number(cases.length)} ca` : `${number(zones.length)} vùng`;
        const countTarget = $('#paTrainingZoneCount');
        if (countTarget) countTarget.textContent = countLabel;
        const status = $('#paTrainingStatus');
        if (status) {
            status.innerHTML = state.paTrainingLoading || state.paTrainingCaseLoading
                ? 'Đang quét H4 và M15 để tìm lệnh đã khớp, chạm SL hoặc TP...'
                : state.paTrainingError || state.paTrainingCaseError
                  ? `<span class="status-error"><strong>${escapeHtml(state.paTrainingError || state.paTrainingCaseError)}</strong></span>`
                  : hasCaseQueue
                    ? `${escapeHtml(state.paTrainingSymbol)} H4: ${number(cases.length)}/${number(state.paTrainingTradeCases.length)} ca theo bộ lọc. Bấm Tiếp hoặc chọn một ca để dạy AI.`
                    : state.paTrainingQueueLoaded
                      ? 'Không còn ca chưa dạy phù hợp trong dữ liệu hiện có. Tải thêm dữ liệu hoặc đổi bộ lọc để tiếp tục.'
                    : 'Chọn cặp tiền rồi bấm Tạo ca để tìm lệnh M15 đã vào và có kết quả SL/TP.';
        }

        if (state.paTrainingQueueLoaded && !hasCaseQueue) {
            list.innerHTML = '<div class="notice">Không còn ca chưa dạy phù hợp trong dữ liệu hiện có.</div>';
            if (detail) detail.innerHTML = '';
            return;
        }

        if (hasCaseQueue) {
            if (!cases.length) {
                list.innerHTML = '<div class="notice">Không có ca nào trong bộ lọc này. Đổi bộ lọc hoặc bấm Tạo ca lại.</div>';
                if (detail) detail.innerHTML = '';
                return;
            }
            if (!state.paTrainingSelectedCaseId || !cases.some((item) => item.id === state.paTrainingSelectedCaseId)) {
                const firstOpen = cases.find((item) => !item.review) || cases[0];
                state.paTrainingSelectedCaseId = firstOpen?.id || '';
                state.paTrainingSelectedZoneId = firstOpen?.zoneId || firstOpen?.zone?.id || '';
            }
            list.innerHTML = cases.map((item, index) => paTrainingCaseCard(item, index)).join('');
            $$('[data-pa-case-id]').forEach((button) => {
                button.addEventListener('click', () => selectPaTrainingCaseById(button.dataset.paCaseId, { focus: true }));
            });
            if (detail) detail.innerHTML = '';
            return;
        }

        if (!zones.length) {
            list.innerHTML = '<div class="notice">Chưa có lệnh M15 đã khớp và chạm SL/TP trong dữ liệu. Hãy kiểm tra dữ liệu H4/M15 hoặc chọn cặp khác.</div>';
            if (detail) detail.innerHTML = '';
            return;
        }
        if (!state.paTrainingSelectedZoneId || !zones.some((zone) => zone.id === state.paTrainingSelectedZoneId)) {
            state.paTrainingSelectedZoneId = zones[0].id;
        }
        list.innerHTML = zones.map((zone) => paTrainingZoneCard(zone)).join('');
        $$('[data-pa-zone-id]').forEach((button) => {
            button.addEventListener('click', () => {
                state.paTrainingSelectedZoneId = button.dataset.paZoneId;
                renderPaTraining();
                loadPaTrainingChartForSelection(state.paTrainingChartTimeframe || 'H4', { silent: true }).catch((e) => showToast(e.message));
            });
        });
        if (detail) renderPaTrainingZoneDetail(paTrainingSelectedZone());
    }
    function paTrainingFilteredZones() {
        const filter = state.paTrainingZoneFilter || 'active';
        return (state.paTrainingZones || []).filter((zone) => {
            if (filter === 'all') return true;
            if (filter === 'active') return !['broken', 'weak'].includes(zone.status);
            if (filter === 'fresh') return zone.status === 'fresh';
            if (filter === 'touched') return zone.status === 'touched';
            if (filter === 'weak') return zone.status === 'weak';
            if (filter === 'broken') return zone.status === 'broken';
            if (filter === 'manual') return !!zone.manual || zone.source === 'manual';
            return true;
        });
    }

    function paTrainingCaseMatchesFilter(item, filter = state.paTrainingZoneFilter || 'active') {
        const status = item.zoneStatus || item.zone?.status || 'active';
        if (filter === 'all') return true;
        if (filter === 'active') return !['broken', 'weak'].includes(status);
        if (filter === 'fresh') return status === 'fresh';
        if (filter === 'touched') return status === 'touched';
        if (filter === 'weak') return status === 'weak';
        if (filter === 'broken') return status === 'broken';
        if (filter === 'manual') return !!item.zone?.manual || item.zone?.source === 'manual';
        return true;
    }

    function paTrainingFilteredCases() {
        const filter = state.paTrainingZoneFilter || 'active';
        return (state.paTrainingTradeCases || []).filter((item) => paTrainingCaseMatchesFilter(item, filter));
    }

    function paTrainingSelectedTeachingCase() {
        const cases = state.paTrainingTradeCases || [];
        return cases.find((item) => item.id === state.paTrainingSelectedCaseId) || paTrainingFilteredCases()[0] || null;
    }

    function paTrainingCaseAnchorTime(item = paTrainingSelectedTeachingCase()) {
        return item?.entryTime || item?.anchorTime || item?.zone?.originCandleTime || item?.zone?.firstTouchTime || '';
    }

    function paTrainingCaseFitsChartData(item, data = state.activeMarketData) {
        if (!item || !data || !sameSymbol(item.symbol, data.symbol)) return false;
        if (String(item.timeframe || '').toUpperCase() === String(data.timeframe || '').toUpperCase()) return true;
        const anchor = timeToSeconds(paTrainingCaseAnchorTime(item));
        const bars = data.bars || [];
        if (!Number.isFinite(anchor) || !bars.length) return false;
        const first = barTimeValue(bars[0]);
        const last = barTimeValue(bars[bars.length - 1]);
        return Number.isFinite(first) && Number.isFinite(last) && anchor >= Math.min(first, last) && anchor <= Math.max(first, last);
    }

    function updatePaTrainingTimeframeNotice(data = state.activeMarketData) {
        const note = $('#paNearbyZonesNote');
        const item = paTrainingSelectedTeachingCase();
        if (!note || state.activeTab !== 'pa-training' || !data) return;
        if (item && sameSymbol(item.symbol, data.symbol) && String(item.timeframe || '').toUpperCase() !== String(data.timeframe || '').toUpperCase() && !paTrainingCaseFitsChartData(item, data)) {
            note.textContent = `Đang xem ${data.timeframe}: thời điểm của ca ${item.timeframe} nằm ngoài khoảng dữ liệu ${data.timeframe} đã tải. Đã ẩn vùng/entry của ca để giữ đúng tỷ lệ giá; tải thêm lịch sử ${data.timeframe} để xem cùng thời điểm.`;
            note.classList.add('is-history-gap');
        } else {
            note.classList.remove('is-history-gap');
            note.textContent = data.paAlignment?.baseline === 'M15' && data.paAlignment?.aligned !== false
                ? `${data.timeframe} · đã căn theo khoảng dữ liệu M15`
                : data.paAlignment?.aligned === false
                  ? `${data.timeframe} · lịch sử không trùng M15, đang hiển thị toàn bộ dữ liệu ${data.timeframe}`
                : `${data.timeframe} · vùng TP theo ca đang chọn`;
        }
    }

    function paTrainingSelectedZone() {
        const selectedCase = paTrainingSelectedTeachingCase();
        if (selectedCase?.zone) return selectedCase.zone;
        return (state.paTrainingZones || []).find((zone) => zone.id === state.paTrainingSelectedZoneId) || paTrainingFilteredZones()[0] || null;
    }

    function selectPaTrainingCaseById(id, options = {}) {
        const item = (state.paTrainingTradeCases || []).find((candidate) => candidate.id === id);
        if (!item) return;
        state.paTrainingSelectedCaseId = item.id;
        state.paTrainingSelectedZoneId = item.zoneId || item.zone?.id || '';
        persistPaWorkspace({ selectedCaseId: item.id });
        state.chartView.selectedTradeId = null;
        renderPaTraining();
        if (options.focus !== false) {
            focusPaTrainingSelectedCase(options.timeframe || state.paTrainingChartTimeframe || 'H4').catch((error) => showToast(error.message));
        }
    }

    async function startPaTrainingCaseQueue() {
        if (!(state.paTrainingTradeCases || []).length) {
            await loadPaTrainingTradeCases();
            return;
        }
        const cases = paTrainingFilteredCases();
        const firstOpen = cases.find((item) => !item.review) || cases[0];
        if (!firstOpen) {
            showToast('Không có ca nào trong bộ lọc hiện tại.');
            return;
        }
        selectPaTrainingCaseById(firstOpen.id, { focus: true, timeframe: 'M15' });
        showToast(`Bắt đầu ca dạy ${Math.max(1, cases.findIndex((item) => item.id === firstOpen.id) + 1)}/${cases.length}`);
    }

    async function focusPaTrainingSelectedCase(timeframe = state.paTrainingChartTimeframe || 'H4') {
        const item = paTrainingSelectedTeachingCase();
        if (!item) return;
        await loadPaTrainingChartForSymbol(item.symbol || state.paTrainingSymbol, timeframe, {
            silent: true,
            anchorTime: item.zone?.originCandleTime || item.anchorTime || item.entryTime,
        });
        focusPaTrainingTeachingCaseWindow(item);
    }

    function focusPaTrainingTeachingCaseWindow(item = paTrainingSelectedTeachingCase(), currentRegionOnly = false) {
        const bars = state.activeMarketData?.bars || [];
        if (!item || !bars.length) return;
        const indexFor = (time, fallback) => {
            const ts = timeToSeconds(time);
            if (Number.isFinite(ts)) {
                let best = 0;
                let bestDistance = Infinity;
                bars.forEach((bar, index) => {
                    const diff = Math.abs(barTimeValue(bar) - ts);
                    if (diff < bestDistance) {
                        bestDistance = diff;
                        best = index;
                    }
                });
                return best;
            }
            return Number.isFinite(Number(fallback)) ? Number(fallback) : 0;
        };
        const entry = clamp(indexFor(item.entryTime || item.anchorTime, item.entryIndex ?? item.anchorIndex), 0, bars.length - 1);
        const exit = currentRegionOnly ? entry : clamp(indexFor(item.exitTime || item.entryTime || item.anchorTime, item.exitIndex ?? entry), 0, bars.length - 1);
        // The H4 zone may have formed months before the M15 fill. Keep the
        // viewport around the actual order and its exit, not the old origin.
        const left = Math.min(entry, exit);
        const right = Math.max(entry, exit);
        const timeframe = String(state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4').toUpperCase();
        // Keep a similar amount of calendar context when switching timeframes.
        // A fixed 80 bars is useful on H4 but leaves M15 far too zoomed in.
        const contextBars = ({ M1: 7200, M5: 2880, M15: 960, M30: 480, H1: 240, H4: 80, D1: 40 })[timeframe] || 120;
        const span = clamp(Math.max(contextBars, (right - left + 1) * 3), Math.min(12, bars.length), bars.length);
        const first = clamp(Math.round(left - span * 0.2), 0, Math.max(0, bars.length - span));
        const offsetLimits = chartRightOffsetLimits(bars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(bars.length - first - span, offsetLimits.min, offsetLimits.max);
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        stopChartReplay(false);
        state.chartView.replay.enabled = true;
        state.chartView.replay.index = item.resultStatus === 'closed' && item.exitTime ? exit : entry;
        state.chartView.replay.time = barTimeValue(bars[state.chartView.replay.index]) + timeframeSeconds(timeframe) - 1;
        renderReplayControls(state.activeMarketData);
        drawMarketChart(state.activeMarketData);
    }

    function paTrainingSelectedTrade() {
        const teachingCase = paTrainingSelectedTeachingCase();
        if (teachingCase) return teachingCase;
        if (!state.activeMarketData) return null;
        const run = activeBacktestRun(getChartAnalytics(state.activeMarketData));
        if (!state.chartView.selectedTradeId) return null;
        return selectedTrade(run);
    }
    function paTrainingSessionKey(trade = paTrainingSelectedTrade(), zone = paTrainingSelectedZone()) {
        const tradeId = trade?.id || '';
        if (tradeId) return `trade:${tradeId}`;
        const zoneId = zone?.id || '';
        if (zoneId) return `zone:${zoneId}`;
        const symbol = trade?.symbol || zone?.symbol || state.paTrainingSymbol || 'GLOBAL';
        const timeframe = trade?.timeframe || zone?.timeframe || state.paTrainingChartTimeframe || 'H4';
        return `chart:${symbol}:${timeframe}`;
    }

    function paTrainingSessionDraft(trade = paTrainingSelectedTrade(), zone = paTrainingSelectedZone()) {
        const key = paTrainingSessionKey(trade, zone);
        return state.paTrainingSessionDrafts?.[key] || null;
    }

    function paTrainingDecisionDisplay(decision, label = '') {
        const map = {
            good_case: { label: label || 'Ca đúng cách trade', className: 'is-good' },
            bad_case: { label: label || 'Bỏ ca này', className: 'is-bad' },
            weak_case: { label: label || 'Ca yếu/cần sửa', className: 'is-weak' },
            session_note: { label: label || 'Lưu phiên trao đổi', className: 'is-neutral' },
            adjusted_case: { label: label || 'Đã điều chỉnh setup', className: 'is-neutral' },
        };
        return map[decision] || { label: label || 'Chưa chọn kết luận', className: 'is-empty' };
    }

    function setPaTrainingSessionDecision(decision, label, note = '') {
        const trade = paTrainingSelectedTrade();
        const zone = paTrainingSelectedZone();
        const key = paTrainingSessionKey(trade, zone);
        state.paTrainingSessionDrafts[key] = {
            decision,
            label: label || decision,
            note: note || state.paTrainingSessionDrafts[key]?.note || '',
            updatedAt: new Date().toISOString(),
        };
        renderPaTrainingSessionStrip();
        showToast(`Đã chọn kết luận tạm: ${label || decision}. Bấm Lưu phiên để gom chat + chart + lệnh.`);
    }

    function renderPaTrainingSessionStrip() {
        const target = $('#paTrainingSessionStrip');
        const trade = paTrainingSelectedTrade();
        const zone = paTrainingSelectedZone();
        const draft = paTrainingSessionDraft(trade, zone);
        const info = paTrainingDecisionDisplay(draft?.decision || '', draft?.label || '');
        const messages = paTrainingCurrentSessionMessages(trade, zone);
        const chart = paTrainingChartSessionSnapshot();
        $$('[data-pa-case-decision]').forEach((button) => {
            button.classList.toggle('is-active', !!draft && button.dataset.paCaseDecision === draft.decision);
        });
        if (!target) return;
        const drawingCount = chart.drawings?.length || 0;
        const indicatorCount = chart.enabledIndicators?.length || 0;
        target.innerHTML = `
            <div class="pa-session-state ${escapeAttr(info.className)}">
                <span>Kết luận tạm</span>
                <strong>${escapeHtml(info.label)}</strong>
            </div>
            <div class="pa-session-meta">
                <span>${number(messages.length)} tin chat</span>
                <span>${number(drawingCount)} hình vẽ</span>
                <span>${number(indicatorCount)} chỉ báo</span>
            </div>
        `;
    }

    function paTrainingCurrentSessionMessages(trade = paTrainingSelectedTrade(), zone = paTrainingSelectedZone()) {
        const context = paTrainingChatContext(zone, trade);
        const tradeId = trade?.id || context.selectedTeachingCaseId || context.selectedTradeId || '';
        return normalizePaTrainingChatMessages(state.paTrainingChatMessages, context).filter((message) => {
            const msgTrade = message.tradeId || message.context?.selectedTeachingCaseId || message.context?.selectedTradeId || '';
            if (tradeId) return msgTrade === tradeId;
            return sameSymbol(message.symbol || message.context?.symbol || '', context.symbol);
        });
    }

    function paTrainingVisibleChatMessages(context = paTrainingChatContext()) {
        return normalizePaTrainingChatMessages(state.paTrainingChatMessages, context)
            .filter((message) => sameSymbol(message.symbol || message.context?.symbol || '', context.symbol));
    }

    function paTrainingSessionAutoNote(messages, draft) {
        if (draft?.note) return draft.note;
        const lastUser = [...messages].reverse().find((item) => item.role === 'user' && item.content);
        return lastUser?.content || draft?.label || 'Lưu phiên dạy ca trade.';
    }

    function paTrainingCompactDrawingSnapshot(drawing) {
        if (!drawing || typeof drawing !== 'object') return null;
        const copy = JSON.parse(JSON.stringify(drawing));
        delete copy._screen;
        delete copy._cache;
        return copy;
    }

    function paTrainingChartSessionSnapshot() {
        const data = state.activeMarketData || state.paTrainingChartData || null;
        const drawings = data ? getChartDrawings(data).map(paTrainingCompactDrawingSnapshot).filter(Boolean).slice(-120) : [];
        const indicators = state.chartView.indicators || {};
        const enabledIndicators = Object.keys(indicators).filter((key) => indicators[key]);
        const layout = state.chartView.lastLayout || {};
        const bars = Array.isArray(data?.bars) ? data.bars : [];
        const first = Number.isFinite(Number(layout.first)) ? Number(layout.first) : null;
        const lastExclusive = Number.isFinite(Number(layout.lastExclusive)) ? Number(layout.lastExclusive) : null;
        const sliceFirst = first === null ? Math.max(0, bars.length - 240) : Math.max(0, Math.floor(first) - 30);
        const sliceLast = lastExclusive === null ? bars.length : Math.min(bars.length, Math.ceil(lastExclusive) + 30);
        const visibleBars = bars.slice(sliceFirst, sliceLast).map((bar, offset) => ({
            index: sliceFirst + offset,
            time: bar.time || bar.timestamp || '',
            open: bar.open,
            high: bar.high,
            low: bar.low,
            close: bar.close,
            volume: bar.volume ?? bar.tickVolume ?? null,
        }));
        return {
            symbol: data?.symbol || state.paTrainingSymbol || '',
            timeframe: data?.timeframe || state.paTrainingChartTimeframe || 'H4',
            barsCount: bars.length,
            visibleRange: {
                first,
                lastExclusive,
                barsOnScreen: state.chartView.barsOnScreen,
                rightOffset: state.chartView.rightOffset,
                priceScale: state.chartView.priceScale,
                priceOffset: state.chartView.priceOffset,
            },
            visibleBarRange: { first: sliceFirst, lastExclusive: sliceLast },
            visibleBars,
            indicators: { ...indicators },
            enabledIndicators,
            indicatorColors: { ...(state.chartView.indicatorColors || {}) },
            indicatorTemplate: state.chartView.indicatorTemplate || 'default',
            drawings,
            selectedDrawingIds: Array.isArray(state.chartView.selectedDrawingIds) ? state.chartView.selectedDrawingIds.slice() : [],
            drawingKey: data ? chartDrawingKey(data) : '',
        };
    }

    async function savePaTrainingCurrentSession(options = {}) {
        if (state.paTrainingSessionSaving) return;
        const trade = paTrainingSelectedTrade();
        const zone = paTrainingSelectedZone();
        if (!trade) {
            showToast('Chưa chọn ca trade để lưu phiên. Bấm Tạo ca/Bắt đầu hoặc chọn một ca trong hàng đợi trước.');
            return;
        }
        const key = paTrainingSessionKey(trade, zone);
        let draft = paTrainingSessionDraft(trade, zone);
        if (!draft?.decision) {
            draft = {
                decision: options.decision || 'session_note',
                label: options.label || 'Lưu phiên trao đổi',
                note: options.note || '',
                updatedAt: new Date().toISOString(),
            };
            state.paTrainingSessionDrafts[key] = draft;
        }
        const input = $('#paTrainingChatInput');
        const finalNote = String(input?.value || '').trim();
        const messages = paTrainingCurrentSessionMessages(trade, zone);
        const chart = paTrainingChartSessionSnapshot();
        const tradeSnapshot = paTrainingTradeSnapshot(trade);
        const note = finalNote || options.note || paTrainingSessionAutoNote(messages, draft);
        const session = {
            id: `pa_session_${Date.now()}_${Math.random().toString(16).slice(2)}`,
            savedAt: new Date().toISOString(),
            decision: draft.decision,
            label: draft.label,
            finalNote,
            chatMessages: messages.map((message) => ({
                role: message.role,
                content: message.content,
                createdAt: message.createdAt,
                symbol: message.symbol,
                timeframe: message.timeframe,
                zoneId: message.zoneId,
                tradeId: message.tradeId,
                provider: message.provider || '',
                context: message.context || {},
            })),
            chatCount: messages.length,
            chart,
        };
        const snapshot = {
            zone: zone || trade.zone || null,
            trade: tradeSnapshot,
            originalTrade: trade.originalTrade || tradeSnapshot,
            session,
        };
        state.paTrainingSessionSaving = true;
        renderPaTrainingSessionStrip();
        try {
            const data = await api('pa_training_feedback_save', {
                kind: 'setup',
                targetId: trade.id || `${trade.symbol || 'trade'}_${trade.entryTime || Date.now()}`,
                symbol: trade.symbol || state.activeMarketData?.symbol || state.paTrainingSymbol || '',
                timeframe: trade.timeframe || state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4',
                decision: draft.decision,
                label: draft.label,
                note,
                snapshot,
                reviewId: trade.savedReviewId || '',
                expectedUpdatedAt: trade.savedReviewUpdatedAt || '',
            });
            state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
            const caseIndex = (state.paTrainingTradeCases || []).findIndex((item) => item.id === trade.id);
            if (caseIndex >= 0) {
                state.paTrainingTradeCases[caseIndex] = { ...state.paTrainingTradeCases[caseIndex], review: data.review, savedReviewId: data.review.id, savedReviewUpdatedAt: data.review.updatedAt || data.review.createdAt, caseStatus: 'reviewed' };
            }
            delete state.paTrainingSessionDrafts[key];
            if (input && finalNote) input.value = '';
            if (options.advance !== false) await loadPaTrainingTradeCases({ silent: true, advanceAfterSave: true });
            else renderPaTraining();
            const next = options.advance === false ? null : paTrainingSelectedTeachingCase();
            showToast(options.advance === false
                ? 'Đã lưu ca.'
                : next
                  ? draft.decision === 'session_note'
                    ? 'Đã lưu phiên. Muốn AI học cách chọn ca, hãy đánh dấu Đúng/Bỏ/Sửa rồi lưu ca.'
                    : `Đã lưu ca. Ca tiếp theo được xếp từ ${number(next.learning?.taughtCount || 0)} ca đã dạy.`
                  : 'Đã lưu ca. Không còn ca chưa dạy tiếp theo trong dữ liệu hiện có.');
        } catch (error) {
            showToast(error.message);
        } finally {
            state.paTrainingSessionSaving = false;
            renderPaTrainingSessionStrip();
        }
    }
    function paTrainingTradeSnapshot(trade) {
        if (!trade) return null;
        return {
            id: trade.id || '',
            symbol: trade.symbol || state.activeMarketData?.symbol || state.paTrainingSymbol || '',
            timeframe: trade.timeframe || state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4',
            contextTimeframe: trade.contextTimeframe || trade.zone?.timeframe || 'H4',
            direction: trade.direction || '',
            anchorTime: trade.anchorTime || '',
            anchorIndex: trade.anchorIndex,
            signalIndex: trade.signalIndex,
            signalTime: trade.signalTime || '',
            zoneTouchCount: trade.zoneTouchCount,
            signalBodyFraction: trade.signalBodyFraction,
            entryIndex: trade.entryIndex,
            entryCandleNumber: trade.entryCandleNumber,
            entryTime: trade.entryTime || '',
            entryPrice: trade.entryPrice,
            exitIndex: trade.exitIndex,
            exitCandleNumber: trade.exitCandleNumber,
            exitTime: trade.exitTime || '',
            exitPrice: trade.exitPrice,
            exitType: trade.exitType || '',
            initialStop: trade.initialStop,
            targetPrice: trade.targetPrice,
            targetPrice2: trade.targetPrice2 ?? null,
            corrected: !!trade.corrected,
            correctionNote: trade.correctionNote || '',
            resultStatus: trade.resultStatus || '',
            sameBarAmbiguous: !!trade.sameBarAmbiguous,
            riskR: trade.riskR,
            rr: trade.rr,
            rrTarget2: trade.rrTarget2,
            netR: trade.netR,
            pnlUsd: trade.pnlUsd,
            mfeR: trade.mfeR,
            maeR: trade.maeR,
            holdingBars: trade.holdingBars,
            reason: trade.reason || '',
            zoneReason: trade.zoneReason || '',
            entryReason: trade.entryReason || '',
            riskReason: trade.riskReason || '',
            exitReason: trade.exitReason || '',
            annotations: trade.annotations || {},
            isTeachingCase: !!trade.isTeachingCase,
            zoneId: trade.zoneId || trade.zone?.id || '',
            zoneType: trade.zoneType || trade.zone?.type || '',
            zoneLow: trade.zoneLow ?? trade.zone?.low,
            zoneHigh: trade.zoneHigh ?? trade.zone?.high,
            zoneOriginIndex: trade.zoneOriginIndex ?? trade.zone?.originIndex,
            zoneOriginCandleNumber: trade.zoneOriginCandleNumber ?? trade.zone?.originCandleNumber,
            zoneOriginTime: trade.zone?.originCandleTime || '',
        };
    }

    function paTrainingCaseLabel(trade) {
        if (!trade) return 'Chưa chọn ca trade';
        const side = displayTradeSide(trade.direction || (trade.zoneType === 'supply' ? 'short' : 'long'));
        const symbol = trade.symbol || state.activeMarketData?.symbol || state.paTrainingSymbol || '';
        const time = formatChartTime(trade.entryTime || trade.anchorTime || '');
        return `${side} ${symbol}${time ? ` | ${time}` : ''}`;
    }

    function paTrainingCaseHasRestorableChanges(trade) {
        const original = trade?.originalTrade;
        if (!original) return false;
        return [
            'entryPrice', 'initialStop', 'targetPrice', 'targetPrice2', 'rr', 'rrTarget2', 'riskR',
            'zoneLow', 'zoneHigh', 'exitIndex', 'exitCandleNumber', 'exitTime', 'exitPrice', 'exitType',
            'resultStatus', 'sameBarAmbiguous', 'netR', 'pnlUsd', 'mfeR', 'maeR', 'holdingBars',
            'corrected', 'correctionNote',
        ].some((key) => {
            const current = trade[key];
            const initial = original[key];
            if (current == null && initial == null) return false;
            const currentNumber = Number(current);
            const initialNumber = Number(initial);
            if (current != null && initial != null && Number.isFinite(currentNumber) && Number.isFinite(initialNumber)) {
                return Math.abs(currentNumber - initialNumber) > Math.max(1e-12, Math.abs(initialNumber) * 1e-12);
            }
            return String(current ?? '') !== String(initial ?? '');
        });
    }

    function paTrainingCaseCard(item, index = 0) {
        const active = item.id === state.paTrainingSelectedCaseId;
        const reviewed = item.review ? 'is-reviewed' : '';
        const sideClass = item.direction === 'short' ? 'supply' : 'demand';
        const sideLabel = item.direction === 'short' ? 'Bán' : 'Mua';
        const outcomeLabel = item.exitType === 'TP1_SL' ? 'TP1 rồi SL' : item.exitType;
        const outcome = ['TP', 'TP1', 'TP2', 'TP1_SL', 'SL'].includes(item.exitType) ? `${outcomeLabel} · ${formatSigned(item.netR || 0, 2)}R` : 'Chưa có kết quả';
        const statusText = item.review ? 'Đã chấm' : 'Chưa chấm';
        return `
            <button class="pa-case-row ${active ? 'is-active' : ''} ${reviewed}" type="button" data-pa-case-id="${escapeAttr(item.id)}">
                <span class="pa-case-number">Ca ${number(index + 1)}</span>
                <span class="pa-case-direction is-${escapeAttr(sideClass)}">${escapeHtml(sideLabel)}</span>
                <strong>${escapeHtml(item.symbol || '')} · H4 → M15 · ${escapeHtml(formatChartTime(item.entryTime || item.anchorTime))}</strong>
                <span class="pa-case-row-meta" title="${escapeAttr(item.learning?.reason || '')}">${escapeHtml(outcome)} · RR 1:${escapeHtml(formatFixed(item.rr ?? 1, 2))} · ${escapeHtml(statusText)}</span>
            </button>
        `;
    }
    function paTrainingZoneCard(zone) {
        const active = zone.id === state.paTrainingSelectedZoneId;
        const reviewed = zone.review ? 'is-reviewed' : '';
        return `
            <button class="pa-zone-card ${active ? 'is-active' : ''} ${reviewed}" type="button" data-pa-zone-id="${escapeAttr(zone.id)}">
                <span class="pa-zone-type is-${escapeAttr(zone.type)}">${escapeHtml(displayZone(zone.type))}</span>
                <strong>${escapeHtml(zone.symbol)} ${escapeHtml(zone.timeframe)} | ${escapeHtml(paTrainingStatusLabel(zone.status))}${zone.manual || zone.source === 'manual' ? ' | Tự thêm' : ''}</strong>
                <small>${formatPrice(zone.low, zone.symbol)} - ${formatPrice(zone.high, zone.symbol)} | chạm ${number(zone.touchedCount)} | điểm ${formatFixed(zone.strengthScore, 1)}</small>
                <small>${escapeHtml(formatDateTime(zone.createdAt))}${zone.review ? ' | đã chấm' : ''}</small>
            </button>
        `;
    }

    function renderPaTrainingZoneDetail(zone) {
        const detail = $('#paTrainingZoneDetail');
        if (!detail) return;
        if (!zone) {
            detail.innerHTML = '<div class="notice">Chọn một vùng để bắt đầu chấm.</div>';
            return;
        }
        detail.innerHTML = `
            <div class="pa-zone-detail">
                <div class="pa-zone-mini-chart is-${escapeAttr(zone.type)}">
                    <div class="pa-zone-band"></div>
                    <div class="pa-zone-line top"></div>
                    <div class="pa-zone-line bottom"></div>
                    <span>${escapeHtml(displayZone(zone.type))} H4</span>
                </div>
                <div class="pa-zone-facts">
                    ${paTrainingFact('Cặp / khung', `${zone.symbol} ${zone.timeframe}`)}
                    ${paTrainingFact('Loại vùng', displayZone(zone.type))}
                    ${paTrainingFact('Giá vùng', `${formatPrice(zone.low, zone.symbol)} - ${formatPrice(zone.high, zone.symbol)}`)}
                    ${paTrainingFact('Trạng thái', paTrainingStatusLabel(zone.status))}
                    ${paTrainingFact('Số lần chạm', number(zone.touchedCount))}
                    ${paTrainingFact('Điểm máy dò', formatFixed(zone.strengthScore, 2))}
                    ${paTrainingFact('Tạo bởi', formatDateTime(zone.createdAt))}
                    ${paTrainingFact('Chạm đầu', zone.firstTouchTime ? formatDateTime(zone.firstTouchTime) : 'Chưa chạm')}
                </div>
                ${zone.manual || zone.source === 'manual' ? `<div class="notice status-ok"><strong>Vùng tự thêm:</strong> Đây là vùng do m chọn hoặc vẽ trên chart. AI sẽ học từ nhận xét của m.</div>` : ''}
                <div class="notice pa-zone-reason">
                    <strong>Lý do máy đề xuất vùng</strong>
                    <span>${escapeHtml(zone.reason || 'Vùng được sinh từ break of structure H4 và cây nến gốc tạo lực.')}</span>
                </div>
                ${zone.review ? `<div class="notice status-ok"><strong>Bạn đã chấm:</strong> ${escapeHtml(zone.review.label || zone.review.decision)}${zone.review.note ? ` | ${escapeHtml(zone.review.note)}` : ''}</div>` : ''}
                <textarea id="paTrainingZoneNote" rows="3" placeholder="Ghi chú cách bạn nhìn vùng này: vì sao chọn/bỏ, vùng đẹp/xấu ở điểm nào...">${escapeHtml(zone.review?.note || '')}</textarea>
                <div class="pa-training-actions">
                    <button class="button success" type="button" data-pa-zone-decision="trade_zone" data-pa-zone-label="Chọn để trade">Chọn để trade</button>
                    <button class="button danger" type="button" data-pa-zone-decision="skip_zone" data-pa-zone-label="Bỏ qua">Bỏ qua</button>
                    <button class="button warning" type="button" data-pa-zone-decision="broken_zone" data-pa-zone-label="Vùng đã bị phá">Vùng đã bị phá</button>
                    <button class="button secondary" type="button" data-pa-zone-decision="weak_zone" data-pa-zone-label="Vùng yếu/nhiễu">Vùng yếu/nhiễu</button>
                    <button class="button secondary" type="button" data-pa-open-chart="H4">Mở chart H4</button>
                    <button class="button secondary" type="button" data-pa-open-chart="M15">Mở chart M15</button>
                </div>
            </div>
        `;
        $$('[data-pa-zone-decision]').forEach((button) => {
            if (button.dataset.paDecisionBound === '1') return;
            button.dataset.paDecisionBound = '1';
            button.addEventListener('click', () => savePaTrainingZoneDecision(button.dataset.paZoneDecision, button.dataset.paZoneLabel).catch((e) => showToast(e.message)));
        });
        $$('[data-pa-open-chart]').forEach((button) => {
            if (button.dataset.paOpenChartBound === '1') return;
            button.dataset.paOpenChartBound = '1';
            button.addEventListener('click', () => openPaTrainingChart(button.dataset.paOpenChart).catch((e) => showToast(e.message)));
        });
    }

    function paTrainingFact(label, value) {
        return `<div class="pa-zone-fact"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
    }

    function paTrainingStatusLabel(status) {
        const map = {
            active: 'Còn dùng được',
            fresh: 'Chưa chạm lại',
            touched: 'Đã chạm lại',
            weak: 'Vùng yếu',
            broken: 'Đã bị phá',
        };
        return map[status] || status || 'Không rõ';
    }


    function renderPaTrainingCoachZoneSummary() {
        const target = $('#paTrainingCoachZoneSummary');
        if (!target) return;
        const zone = paTrainingSelectedZone();
        const trade = paTrainingSelectedTrade();
        if (!zone && !trade) {
            target.innerHTML = '';
            return;
        }
        if (trade) {
            const isTeaching = !!trade.isTeachingCase;
            const hasOutcome = (['TP', 'TP1', 'TP2', 'TP1_SL', 'SL'].includes(trade.exitType)) && Number.isFinite(Number(trade.netR));
            const resultClass = isTeaching
                ? (hasOutcome ? (Number(trade.netR) >= 0 ? 'demand' : 'supply') : (trade.direction === 'short' ? 'supply' : 'demand'))
                : Number(trade.netR) >= 0 ? 'demand' : 'supply';
            const exitLabel = trade.exitType === 'TP1_SL' ? 'TP1 rồi SL' : trade.exitType;
            const resultText = isTeaching
                ? (hasOutcome ? `${exitLabel} · ${formatSigned(trade.netR, 2)}R · ${trade.review ? 'Đã chấm' : 'Chưa chấm'}` : (trade.review ? `Đã chấm: ${trade.review.label || trade.review.decision}` : 'Chưa có kết quả sau khi sửa'))
                : `${formatSigned(trade.netR || 0, 2)}R`;
            const zoneText = zone
                ? `${displayZone(zone.type || trade.zoneType)} ${formatPrice(zone.low ?? trade.zoneLow, trade.symbol)} - ${formatPrice(zone.high ?? trade.zoneHigh, trade.symbol)}`
                : 'Chưa gắn vùng H4 cụ thể';
            const zoneReason = trade.zoneReason || zone?.reason || 'Vùng được chọn theo phản ứng giá H4.';
            const entryReason = trade.entryReason || trade.reason || 'Chưa có lý do vào lệnh.';
            const riskReason = trade.riskReason || `SL ${formatPrice(trade.initialStop, trade.symbol)} · TP ${formatPrice(trade.targetPrice, trade.symbol)}`;
            const exitReason = trade.exitReason || 'Chưa có kết quả thoát lệnh.';
            const canRestoreLevels = isTeaching && paTrainingCaseHasRestorableChanges(trade);
            const caseKey = paTrainingSessionKey(trade, zone);
            const detailsExpanded = state.paTrainingExpandedCaseId === caseKey;
            target.innerHTML = `
                <div class="pa-ai-zone-card is-${escapeAttr(resultClass)} is-case-card">
                    <div class="pa-ai-case-summary">
                        <span>${isTeaching ? 'CA DẠY ĐANG XEM' : 'CA TRADE ĐANG XEM'}</span>
                        <strong title="${escapeAttr(paTrainingCaseLabel(trade))}">${escapeHtml(paTrainingCaseLabel(trade))}</strong>
                    </div>
                    <button class="pa-ai-case-toggle" type="button" data-pa-case-details-toggle aria-expanded="${detailsExpanded}" aria-label="${detailsExpanded ? 'Thu gọn chi tiết ca' : 'Mở chi tiết ca'}" title="${detailsExpanded ? 'Thu gọn chi tiết ca' : 'Mở chi tiết ca'}"><span aria-hidden="true"></span></button>
                    <div class="pa-ai-case-details"${detailsExpanded ? '' : ' hidden'}>
                        <small>${trade.corrected ? 'Đã sửa setup' : 'Đã vào'} · RR đến TP: ${escapeHtml(formatFixed(trade.rr ?? 1, 2))}R</small>
                        <div class="pa-case-levels" aria-label="Các mức giá của lệnh">
                            <span><b>Entry</b><strong>${escapeHtml(formatPrice(trade.entryPrice, trade.symbol))}</strong></span>
                            <span><b>SL</b><strong>${escapeHtml(formatPrice(trade.initialStop, trade.symbol))}</strong></span>
                            <span><b>TP · ${escapeHtml(formatFixed(trade.rr ?? 1, 2))}R</b><strong>${escapeHtml(formatPrice(trade.targetPrice, trade.symbol))}</strong></span>
                        </div>
                        ${canRestoreLevels ? `<div class="pa-case-level-reset-row"><button class="button secondary pa-case-level-reset" type="button" data-pa-case-level-reset title="Khôi phục Entry, SL, TP và kết quả ban đầu của ca" aria-label="Khôi phục Entry, SL, TP và kết quả ban đầu của ca">↶ Khôi phục gốc</button></div>` : ''}
                        <small>Kéo đường Entry, SL hoặc TP trên biểu đồ để chỉnh mức giá.</small>
                        <small>${escapeHtml(resultText)} | ${escapeHtml(zoneText)}</small>
                        ${hasOutcome ? `<small>Thoát ${escapeHtml(formatPrice(trade.exitPrice, trade.symbol))} · ${escapeHtml(formatChartTime(trade.exitTime || ''))}</small>` : ''}
                        <div class="pa-ai-case-reasons">
                            ${trade.learning?.reason ? `<div><b>AI chọn vì</b><span>${escapeHtml(trade.learning.reason)}</span></div>` : ''}
                            <div><b>Vùng / lý do</b><span>${escapeHtml(zoneText)} · ${escapeHtml(zoneReason)}</span></div>
                            <div><b>Lý do vào</b><span>${escapeHtml(entryReason)}</span></div>
                            <div><b>SL / TP</b><span>${escapeHtml(riskReason)}</span></div>
                            <div><b>Thoát lệnh</b><span>${escapeHtml(exitReason)}</span></div>
                        </div>
                    </div>
                </div>
            `;
            return;
        }
        target.innerHTML = `
            <div class="pa-ai-zone-card is-${escapeAttr(zone.type)}">
                <span>${escapeHtml(displayZone(zone.type))} ${escapeHtml(zone.timeframe || 'H4')}</span>
                <strong>${escapeHtml(zone.symbol || '')}</strong>
                <small>${formatPrice(zone.low, zone.symbol)} - ${formatPrice(zone.high, zone.symbol)} | ${escapeHtml(paTrainingStatusLabel(zone.status))}</small>
                <small>${zone.manual || zone.source === 'manual' ? 'Vùng m tự thêm' : `Điểm máy dò ${formatFixed(zone.strengthScore, 1)}`}</small>
            </div>
        `;
    }
    function paTrainingRelevantLessonsForContext(zone = paTrainingSelectedZone(), trade = paTrainingSelectedTrade(), limit = 4) {
        const memory = normalizePaTrainingMemory(state.paTrainingMemory);
        const lessons = Array.isArray(memory.lessons) ? memory.lessons : [];
        if (!lessons.length) return [];
        const context = paTrainingChatContext(zone, trade);
        const symbol = String(context.symbol || '').toUpperCase();
        const timeframe = String(context.timeframe || '').toUpperCase();
        const kind = trade ? 'trade_case' : zone ? 'zone' : 'general';
        const zoneType = String(zone?.type || '').toLowerCase();
        const contextTags = new Set([kind]);
        if (symbol) contextTags.add(`symbol:${symbol.toLowerCase()}`);
        if (timeframe) contextTags.add(`tf:${timeframe.toLowerCase()}`);
        if (zoneType === 'supply') contextTags.add('canh_sell');
        if (zoneType === 'demand') contextTags.add('canh_buy');

        return lessons.map((lesson) => {
            const tags = Array.isArray(lesson.tags) ? lesson.tags : [];
            let score = Number(lesson.confidence || 3);
            if (symbol && String(lesson.symbol || '').toUpperCase() === symbol) score += 8;
            if (timeframe && String(lesson.timeframe || '').toUpperCase() === timeframe) score += 4;
            if (lesson.kind === kind) score += 6;
            if (lesson.kind === 'general') score += 2;
            tags.forEach((tag) => {
                if (contextTags.has(String(tag))) score += 3;
            });
            return { ...lesson, _score: score };
        })
            .sort((a, b) => (b._score - a._score) || String(b.createdAt || '').localeCompare(String(a.createdAt || '')))
            .slice(0, limit);
    }

    function renderPaTrainingRelevantLessons() {
        const target = $('#paTrainingRelevantLessons');
        if (!target) return;
        const lessons = paTrainingRelevantLessonsForContext();
        const total = normalizePaTrainingMemory(state.paTrainingMemory).lessons.length;
        if (!lessons.length) {
            target.innerHTML = total
                ? '<div class="pa-ai-lessons-empty">Có bài học trong bộ nhớ, nhưng chưa có bài nào khớp mạnh với ca hiện tại.</div>'
                : '<div class="pa-ai-lessons-empty">Chưa có bài học PA. Chat/chấm ca rồi bấm Học để AI nhớ cách nhìn của m.</div>';
            return;
        }
        target.innerHTML = `
            <div class="pa-ai-lessons-head"><strong>AI đang dùng ${number(lessons.length)} bài học</strong><span>${number(total)} tổng</span></div>
            ${lessons.map((lesson) => `
                <div class="pa-ai-lesson-chip" title="${escapeAttr(lesson.appliesWhen || '')}">
                    <strong>${escapeHtml(lesson.title || 'Bài học')}</strong>
                    <span>${escapeHtml(lesson.summary || '')}</span>
                </div>
            `).join('')}
        `;
    }

    function paTrainingLessonSourceText() {
        const typed = String($('#paTrainingChatInput')?.value || '').trim();
        if (typed) return typed;
        return state.paTrainingChatMessages
            .slice(-6)
            .map((item) => `${item.role === 'user' ? 'Bạn' : 'AI'}: ${item.content || ''}`)
            .join('\n')
            .trim();
    }

    async function savePaTrainingLessonFromChat() {
        const zone = paTrainingSelectedZone();
        const trade = paTrainingSelectedTrade();
        const tradeSnapshot = paTrainingTradeSnapshot(trade);
        const context = paTrainingChatContext(zone, trade, tradeSnapshot);
        const sourceText = paTrainingLessonSourceText();
        if (!sourceText) {
            showToast('Chưa có đoạn chat hoặc ghi chú để lưu thành bài học.');
            return;
        }
        const data = await api('pa_training_lesson_save', {
            sourceText,
            zone: zone || null,
            trade: tradeSnapshot,
            context,
        });
        if (data.pa_training_memory) {
            state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
        }
        renderPaTraining();
        showToast(data.lesson?.title ? `Đã lưu bài học: ${data.lesson.title}` : 'Đã lưu bài học Price Action');
    }
    function mountSharedMarketChart(tab = state.activeTab || 'mt5') {
        const panel = $('#marketChartPanel');
        const mt5Mount = $('#mt5ChartMount');
        const paMount = $('#paTrainingChartMount');
        if (!panel || !mt5Mount || !paMount) return;
        const usePa = tab === 'pa-training';
        const target = usePa ? paMount : mt5Mount;
        if (!target.contains(panel)) {
            target.innerHTML = '';
            target.appendChild(panel);
        }
        panel.classList.toggle('is-pa-embedded', usePa);
        if (state.activeMarketData) {
            setTimeout(() => drawMarketChart(state.activeMarketData), 0);
        }
    }

    function renderPaTrainingChart() {
        mountSharedMarketChart(state.activeTab === 'pa-training' ? 'pa-training' : 'mt5');
        return;
        const canvas = $('#paTrainingChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.max(720, Math.floor((rect.width || 720) * dpr));
        canvas.height = Math.max(420, Math.floor((rect.height || 420) * dpr));
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        const width = canvas.width / dpr;
        const height = canvas.height / dpr;
        const dark = true;
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = dark ? '#111827' : '#f8fafc';
        ctx.fillRect(0, 0, width, height);

        const zone = paTrainingSelectedZone();
        const data = state.paTrainingChartData;
        const title = $('#paTrainingChartTitle');
        const meta = $('#paTrainingChartMeta');
        const timeframe = state.paTrainingChartTimeframe || 'H4';
        const chartSymbol = zone?.symbol || data?.symbol || state.paTrainingSymbol || '';
        if (title) title.textContent = chartSymbol ? `${chartSymbol} ${timeframe}` : 'Chưa có chart';
        if (meta) {
            meta.textContent = state.paTrainingChartLoading
                ? 'Đang tải dữ liệu nến...'
                : state.paTrainingChartError
                  ? state.paTrainingChartError
                  : data?.bars?.length
                    ? `${number(data.bars.length)} nến | ${zone ? `${formatPrice(zone.low, zone.symbol)} - ${formatPrice(zone.high, zone.symbol)}` : 'chưa chọn vùng'}`
                    : 'Tải vùng H4 để bắt đầu';
        }
        $('#paTrainingChartH4Btn')?.classList.toggle('is-active', timeframe === 'H4');
        $('#paTrainingChartM15Btn')?.classList.toggle('is-active', timeframe === 'M15');

        if (state.paTrainingChartLoading) {
            drawPaChartEmpty(ctx, width, height, 'Đang tải dữ liệu chart...');
            return;
        }
        const bars = data?.bars || [];
        if (!bars.length) {
            drawPaChartEmpty(ctx, width, height, zone ? `${zone.symbol} ${timeframe} chưa được tải. Bấm H4/M15 hoặc Tải vùng H4.` : 'Chọn cặp tiền hoặc tải vùng H4 để xem chart.');
            return;
        }
        if (data?.timeframe !== timeframe || (chartSymbol && data?.symbol !== chartSymbol)) {
            drawPaChartEmpty(ctx, width, height, `${chartSymbol || 'Chart'} ${timeframe} chưa được tải. Bấm H4/M15 hoặc Tải vùng H4.`);
            return;
        }

        const visible = paChartVisibleBars(bars, zone, timeframe);
        const prices = visible.flatMap((bar) => [Number(bar.high), Number(bar.low)]).filter(Number.isFinite);
        if (zone && Number.isFinite(Number(zone.low)) && Number.isFinite(Number(zone.high))) {
            prices.push(Number(zone.low), Number(zone.high));
        }
        const min = Math.min(...prices);
        const max = Math.max(...prices);
        const pad = Math.max((max - min) * 0.12, Math.abs(max || 1) * 0.0008);
        const lo = min - pad;
        const hi = max + pad;
        const plot = { left: 54, right: 64, top: 28, bottom: 46 };
        const plotW = width - plot.left - plot.right;
        const plotH = height - plot.top - plot.bottom;
        const y = (price) => plot.top + ((hi - price) / Math.max(hi - lo, 0.0000001)) * plotH;
        const x = (i) => plot.left + (i / Math.max(1, visible.length - 1)) * plotW;

        ctx.strokeStyle = '#263244';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 6; i++) {
            const yy = plot.top + (i / 6) * plotH;
            ctx.beginPath();
            ctx.moveTo(plot.left, yy);
            ctx.lineTo(width - plot.right, yy);
            ctx.stroke();
            const price = hi - (i / 6) * (hi - lo);
            ctx.fillStyle = '#93a4ba';
            ctx.font = '12px Segoe UI, Arial';
            ctx.fillText(formatPrice(price, chartSymbol), width - plot.right + 8, yy + 4);
        }
        for (let i = 0; i <= 8; i++) {
            const xx = plot.left + (i / 8) * plotW;
            ctx.beginPath();
            ctx.moveTo(xx, plot.top);
            ctx.lineTo(xx, height - plot.bottom);
            ctx.stroke();
        }

        if (zone) {
            const zoneTop = y(Math.max(Number(zone.low), Number(zone.high)));
            const zoneBottom = y(Math.min(Number(zone.low), Number(zone.high)));
            ctx.save();
            ctx.fillStyle = zone.type === 'supply' ? 'rgba(239, 68, 68, 0.18)' : 'rgba(16, 185, 129, 0.18)';
            ctx.strokeStyle = zone.type === 'supply' ? '#ef4444' : '#10b981';
            ctx.setLineDash([7, 6]);
            ctx.fillRect(plot.left, zoneTop, plotW, Math.max(2, zoneBottom - zoneTop));
            ctx.strokeRect(plot.left, zoneTop, plotW, Math.max(2, zoneBottom - zoneTop));
            ctx.setLineDash([]);
            ctx.fillStyle = zone.type === 'supply' ? '#f87171' : '#34d399';
            ctx.font = '800 13px Segoe UI, Arial';
            ctx.fillText(`${displayZone(zone.type)} ${zone.timeframe || 'H4'}`, plot.left + 12, zoneTop + 20);
            ctx.restore();
        }

        const allBars = data.bars || [];
        const startIndex = Math.max(0, allBars.indexOf(visible[0]));
        const indicators = buildChartIndicators(allBars);
        drawPaPriceSeries(ctx, visible, indicators.ema200.slice(startIndex, startIndex + visible.length), x, y, '#e5e7eb', 1.6);
        drawPaSwingLine(ctx, visible, x, y);

        const candleW = clamp(plotW / Math.max(visible.length, 1) * 0.58, 3, 10);
        visible.forEach((bar, index) => {
            const open = Number(bar.open);
            const close = Number(bar.close);
            const high = Number(bar.high);
            const low = Number(bar.low);
            const up = close >= open;
            const xx = x(index);
            ctx.strokeStyle = up ? '#10b981' : '#ef4444';
            ctx.fillStyle = up ? '#10b981' : '#ef4444';
            ctx.beginPath();
            ctx.moveTo(xx, y(high));
            ctx.lineTo(xx, y(low));
            ctx.stroke();
            const bodyTop = y(Math.max(open, close));
            const bodyBottom = y(Math.min(open, close));
            ctx.fillRect(xx - candleW / 2, bodyTop, candleW, Math.max(2, bodyBottom - bodyTop));
        });

        ctx.fillStyle = '#cbd5e1';
        ctx.font = '600 13px Segoe UI, Arial';
        ctx.fillText(`${chartSymbol} ${timeframe} | EMA200${zone ? ' | vùng đang dạy' : ''}`, plot.left, height - 18);
    }

    function drawPaChartEmpty(ctx, width, height, text) {
        ctx.fillStyle = '#94a3b8';
        ctx.font = '700 18px Segoe UI, Arial';
        ctx.textAlign = 'center';
        ctx.fillText(text, width / 2, height / 2);
        ctx.textAlign = 'left';
    }

    function paChartVisibleBars(bars, zone, timeframe) {
        const count = Math.min(timeframe === 'M15' ? 220 : 150, bars.length);
        let center = bars.length - Math.floor(count / 2);
        const anchorTime = zone?.firstTouchTime || zone?.originCandleTime || zone?.createdAt;
        const anchorTs = timeToSeconds(anchorTime);
        if (Number.isFinite(anchorTs)) {
            let best = 0;
            let bestDistance = Infinity;
            bars.forEach((bar, index) => {
                const diff = Math.abs(barTimeValue(bar) - anchorTs);
                if (diff < bestDistance) {
                    bestDistance = diff;
                    best = index;
                }
            });
            center = best;
        }
        const start = clamp(Math.round(center - count * 0.45), 0, Math.max(0, bars.length - count));
        return bars.slice(start, start + count);
    }

    function drawPaPriceSeries(ctx, visibleBars, series, x, y, color, width = 1.5) {
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = width;
        ctx.beginPath();
        let started = false;
        series.forEach((value, index) => {
            if (!Number.isFinite(Number(value))) return;
            const xx = x(index);
            const yy = y(Number(value));
            if (!started) {
                ctx.moveTo(xx, yy);
                started = true;
            } else {
                ctx.lineTo(xx, yy);
            }
        });
        if (started) ctx.stroke();
        ctx.restore();
    }

    function drawPaSwingLine(ctx, visibleBars, x, y) {
        if (visibleBars.length < 8) return;
        ctx.save();
        ctx.strokeStyle = '#f59e0b';
        ctx.lineWidth = 1.4;
        ctx.setLineDash([6, 6]);
        ctx.beginPath();
        const step = Math.max(8, Math.floor(visibleBars.length / 7));
        let started = false;
        for (let i = 0; i < visibleBars.length; i += step) {
            const bar = visibleBars[i];
            const price = i % (step * 2) === 0 ? Number(bar.low) : Number(bar.high);
            if (!Number.isFinite(price)) continue;
            if (!started) {
                ctx.moveTo(x(i), y(price));
                started = true;
            } else {
                ctx.lineTo(x(i), y(price));
            }
        }
        if (started) ctx.stroke();
        ctx.restore();
    }

    async function loadPaTrainingChartForSelection(timeframe = 'H4', options = {}) {
        const teachingCase = paTrainingSelectedTeachingCase();
        const zone = paTrainingSelectedZone();
        const symbol = teachingCase?.symbol || zone?.symbol || state.paTrainingSymbol;
        if (!symbol) return;
        return loadPaTrainingChartForSymbol(symbol, timeframe, {
            ...options,
            anchorTime: options.anchorTime || teachingCase?.anchorTime || teachingCase?.entryTime || zone?.firstTouchTime || zone?.originCandleTime || zone?.createdAt,
        });
    }

    async function loadPaTrainingChartForSymbol(symbol, timeframe = 'H4', options = {}) {
        const requestedTimeframe = String(timeframe || 'H4').toUpperCase();
        const normalizedSymbol = normalizeSymbolKey(symbol);
        const requestId = state.paTrainingChartRequestId = (state.paTrainingChartRequestId || 0) + 1;
        const isCurrentRequest = () => state.paTrainingChartRequestId === requestId;
        state.paTrainingChartTimeframe = requestedTimeframe;
        const file = findBestMarketFileForSymbol(normalizedSymbol, requestedTimeframe);
        if (!file) {
            const label = normalizedSymbol || String(symbol || '').toUpperCase();
            if (isCurrentRequest()) {
                state.paTrainingChartData = null;
                state.paTrainingChartLoading = false;
                state.paTrainingChartError = label + ' ' + requestedTimeframe + ' chưa có dữ liệu.';
                renderPaTrainingChart();
                if (!options.silent) showToast(label + ' chưa có dữ liệu chart. Vào Dữ liệu & MT5 để lấy thêm.');
            }
            return false;
        }
        const resolvedSymbol = file.symbol || normalizedSymbol;
        const resolvedTimeframe = String(file.timeframe || requestedTimeframe).toUpperCase();
        const rememberedViewport = savedPaWorkspace(resolvedSymbol, state.paTrainingTimeframe);
        state.paTrainingChartTimeframe = resolvedTimeframe;
        state.paTrainingChartLoading = true;
        state.paTrainingChartError = '';
        renderPaTrainingChart();
        try {
            if (state.activeTab === 'pa-training') mountSharedMarketChart('pa-training');
            const chartResponse = await api('market_data', { file: file.file });
            if (!isCurrentRequest()) return false;
            let chartData = chartResponse.market_data;
            if (resolvedTimeframe !== 'M15') {
                const m15File = findMarketFile(resolvedSymbol, 'M15');
                if (m15File) {
                    const m15Response = await api('market_data', { file: m15File.file });
                    if (!isCurrentRequest()) return false;
                    const m15Bars = m15Response.market_data?.bars || [];
                    let from = Infinity;
                    let to = -Infinity;
                    for (const bar of m15Bars) {
                        const time = barTimeValue(bar);
                        if (!Number.isFinite(time)) continue;
                        from = Math.min(from, time);
                        to = Math.max(to, time);
                    }
                    if (Number.isFinite(from) && Number.isFinite(to)) {
                        const timeframeSeconds = ({ M1: 60, M5: 300, M15: 900, M30: 1800, H1: 3600, H4: 14400, D1: 86400 })[resolvedTimeframe] || 1;
                        const alignedBars = (chartData.bars || [])
                            .filter((bar) => {
                                const time = barTimeValue(bar);
                                return Number.isFinite(time) && time + timeframeSeconds > from && time <= to;
                            })
                            .map((bar, index) => ({ ...bar, index }));
                        chartData = alignedBars.length
                            ? {
                                ...chartData,
                                bars: alignedBars,
                                paAlignment: { baseline: 'M15', aligned: true, from, to, referenceBars: m15Bars.length },
                            }
                            : {
                                ...chartData,
                                paAlignment: { baseline: 'M15', aligned: false, referenceBars: m15Bars.length },
                            };
                    }
                }
            }
            if (!isCurrentRequest()) return false;
            state.chartView.timeframe = resolvedTimeframe;
            await loadMarketData(file.file, { marketData: chartData });
            if (!isCurrentRequest()) return false;
            state.paTrainingSymbol = resolvedSymbol;
            state.paTrainingChartData = state.activeMarketData;
            state.paTrainingChartError = '';
            restorePaTrainingChartViewport(state.activeMarketData, rememberedViewport);
            const teachingCase = paTrainingSelectedTeachingCase();
            const zone = paTrainingSelectedZone();
            const anchor = options.anchorTime || teachingCase?.anchorTime || teachingCase?.entryTime || zone?.firstTouchTime || zone?.originCandleTime || zone?.createdAt;
            const changedCaseTimeframe = teachingCase
                && String(teachingCase.timeframe || '').toUpperCase() !== String(resolvedTimeframe).toUpperCase();
            if (changedCaseTimeframe && paTrainingCaseFitsChartData(teachingCase, state.activeMarketData)) {
                focusPaTrainingTeachingCaseWindow(teachingCase);
            } else if (changedCaseTimeframe) {
                const bars = state.activeMarketData?.bars || [];
                const span = clamp(220, Math.min(12, bars.length), Math.max(12, bars.length));
                state.chartView.barsOnScreen = span;
                state.chartView.rightOffset = 0;
                state.chartView.priceScale = 1;
                state.chartView.priceOffset = 0;
                state.chartView.replay.enabled = false;
                state.chartView.replay.index = Math.max(0, bars.length - 1);
                renderReplayControls(state.activeMarketData);
                drawMarketChart(state.activeMarketData);
            } else if (anchor) {
                focusChartTime(timeToSeconds(anchor));
            }
            persistPaWorkspace({ chartTimeframe: resolvedTimeframe, selectedCaseId: state.paTrainingSelectedCaseId });
            return true;
        } catch (error) {
            if (!isCurrentRequest()) return false;
            state.paTrainingChartError = error.message;
            if (!options.silent) showToast(error.message);
            return false;
        } finally {
            if (isCurrentRequest()) {
                state.paTrainingChartLoading = false;
                renderPaTrainingChart();
            }
        }
    }
    function paTrainingChatContext(zone = paTrainingSelectedZone(), trade = paTrainingSelectedTrade(), tradeSnapshot = null) {
        const snapshot = tradeSnapshot || paTrainingTradeSnapshot(trade);
        const teachingCase = trade?.isTeachingCase ? trade : paTrainingSelectedTeachingCase();
        return {
            symbol: snapshot?.symbol || zone?.symbol || state.activeMarketData?.symbol || state.paTrainingSymbol || '',
            timeframe: snapshot?.timeframe || zone?.timeframe || state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4',
            hasSelectedZone: !!zone,
            hasSelectedTrade: !!trade,
            hasTeachingCase: !!teachingCase,
            selectedZoneId: zone?.id || teachingCase?.zoneId || '',
            selectedTradeId: trade?.id || '',
            selectedTeachingCaseId: teachingCase?.id || '',
            selectedCase: trade ? paTrainingCaseLabel(trade) : '',
        };
    }
    function paTrainingChartQuestionAttachment(includeChart = false) {
        const canvas = document.querySelector('#marketChart');
        const all = (state.activeMarketData ? getChartDrawings(state.activeMarketData) : []).filter((drawing) => !drawing.hidden);
        const selected = selectedDrawingObjects().filter((drawing) => !drawing.hidden);
        const drawings = (selected.length ? selected : all.slice(-1)).slice(0, 12).map((drawing) => {
            const details = {};
            ['type', 'label', 'text', 'price', 'startPrice', 'endPrice', 'entry', 'stopLoss', 'takeProfit', 'startTime', 'endTime', 'startBar', 'endBar', 'points', 'point', 'color'].forEach((key) => {
                if (drawing[key] !== undefined) details[key] = drawing[key];
            });
            return { id: getDrawingId(drawing), ...details };
        });
        let image = '';
        if (canvas && (drawings.length || includeChart)) {
            try {
                const maxSide = 1280;
                const scale = Math.min(1, maxSide / Math.max(canvas.width, canvas.height));
                const copy = document.createElement('canvas');
                copy.width = Math.max(1, Math.round(canvas.width * scale));
                copy.height = Math.max(1, Math.round(canvas.height * scale));
                copy.getContext('2d')?.drawImage(canvas, 0, 0, copy.width, copy.height);
                image = copy.toDataURL('image/jpeg', 0.68);
                if (image.length > 3_500_000) image = '';
            } catch (_) { image = ''; }
        }
        if (!drawings.length && !image && !includeChart) return null;
        const layout = state.chartView.lastLayout;
        const bars = state.activeMarketData?.bars || [];
        const first = Math.max(0, Math.floor(Number(layout?.first) || 0));
        const lastExclusive = Math.min(bars.length, Math.max(first, Math.ceil(Number(layout?.lastExclusive) || bars.length)));
        const visible = bars.slice(first, lastExclusive);
        const stride = Math.max(1, Math.ceil(visible.length / 120));
        const visibleBars = visible.map((bar, index) => ({ bar, barIndex: first + index })).filter((item, index) => index % stride === 0 || index === visible.length - 1).map(({ bar, barIndex }) => ({
            barIndex,
            time: bar.time_iso || bar.time || '',
            open: Number(bar.open),
            high: Number(bar.high),
            low: Number(bar.low),
            close: Number(bar.close),
        }));
        return {
            source: 'chart_annotation',
            symbol: state.activeMarketData?.symbol || state.paTrainingSymbol || '',
            timeframe: state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4',
            drawings,
            barCount: bars.length,
            visibleFirst: first,
            visibleLastExclusive: lastExclusive,
            priceLow: Number(layout?.lo),
            priceHigh: Number(layout?.hi),
            visibleBars,
            imageDataUrl: image,
        };
    }
    function paTrainingChatScopeKey(context = {}) {
        const symbol = String(context.symbol || 'GLOBAL').toUpperCase().replace(/[^A-Z0-9]/g, '') || 'GLOBAL';
        return symbol;
    }

    function paTrainingChatMessageId() {
        return `pa_chat_${Date.now()}_${Math.random().toString(16).slice(2)}`;
    }

    function normalizePaTrainingChatMessages(messages, fallbackContext = {}) {
        const source = Array.isArray(messages) ? messages : [];
        return source.map((item) => {
            if (!item || typeof item !== 'object') return null;
            const content = String(item.content || '').trim();
            if (!content) return null;
            const role = item.role === 'user' ? 'user' : item.role === 'error' ? 'error' : 'assistant';
            const context = item.context && typeof item.context === 'object' ? item.context : fallbackContext;
            return {
                id: item.id || paTrainingChatMessageId(),
                role,
                content,
                createdAt: item.createdAt || item.created_at || new Date().toISOString(),
                symbol: item.symbol || context.symbol || fallbackContext.symbol || '',
                timeframe: item.timeframe || context.timeframe || fallbackContext.timeframe || 'H4',
                zoneId: item.zoneId || item.zone_id || context.selectedZoneId || '',
                tradeId: item.tradeId || item.trade_id || context.selectedTradeId || '',
                provider: item.provider || '',
                context,
            };
        }).filter(Boolean)
            .sort((a, b) => String(a.createdAt || '').localeCompare(String(b.createdAt || '')))
            .slice(-120);
    }

    function readPaTrainingChatLocalStore() {
        try {
            const parsed = JSON.parse(localStorage.getItem(PA_CHAT_STORAGE_KEY) || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function loadPaTrainingChatLocalMessages(scopeKey, context = {}) {
        const store = readPaTrainingChatLocalStore();
        const symbol = String(context.symbol || 'GLOBAL').toUpperCase().replace(/[^A-Z0-9]/g, '') || 'GLOBAL';
        const timeframes = ['M1', 'M5', 'M15', 'M30', 'H1', 'H4', 'D1', 'W1', 'MN1'];
        const legacyKeys = Object.keys(store).filter((key) => timeframes.some((timeframe) => key === `${symbol}_${timeframe}` || key.startsWith(`${symbol}_${timeframe}_`)));
        const messages = [scopeKey, ...legacyKeys].flatMap((key) => Array.isArray(store[key]) ? store[key] : []);
        const unique = [...new Map(messages.map((message) => [message.id || `${message.role}:${message.createdAt}:${message.content}`, message])).values()];
        return normalizePaTrainingChatMessages(unique, context);
    }

    function persistPaTrainingChatMessages(scopeKey = '', messages = state.paTrainingChatMessages, context = paTrainingChatContext()) {
        try {
            const key = scopeKey || paTrainingChatScopeKey(context);
            const store = readPaTrainingChatLocalStore();
            store[key] = normalizePaTrainingChatMessages(messages, context).slice(-120);
            localStorage.setItem(PA_CHAT_STORAGE_KEY, JSON.stringify(store));
        } catch (error) {
            // Chat history also lives on the backend; localStorage is only a fast fallback.
        }
    }

    function ensurePaTrainingChatHistoryLoaded() {
        if (state.paTrainingChatLoading) return;
        const zone = paTrainingSelectedZone();
        const trade = paTrainingSelectedTrade();
        const context = paTrainingChatContext(zone, trade);
        const scopeKey = paTrainingChatScopeKey(context);
        if (state.paTrainingChatLoadedKey === scopeKey || state.paTrainingChatHistoryLoadingKey === scopeKey) return;

        state.paTrainingChatLoadedKey = scopeKey;
        state.paTrainingChatMessages = loadPaTrainingChatLocalMessages(scopeKey, context);
        state.paTrainingChatHistoryLoadingKey = scopeKey;
        const requestRevision = state.paTrainingChatRevision;
        api('pa_training_chat_history', { context })
            .then((data) => {
                if (state.paTrainingChatLoadedKey !== scopeKey || state.paTrainingChatRevision !== requestRevision) return;
                if (Array.isArray(data.messages)) {
                    state.paTrainingChatMessages = normalizePaTrainingChatMessages(data.messages, context);
                    persistPaTrainingChatMessages(scopeKey, state.paTrainingChatMessages, context);
                }
            })
            .catch(() => {
                // Keep the local copy visible if the history endpoint is unavailable.
            })
            .finally(() => {
                if (state.paTrainingChatHistoryLoadingKey === scopeKey) {
                    state.paTrainingChatHistoryLoadingKey = '';
                    renderPaTrainingChat();
                }
            });
    }

    function paTrainingChatMessageHtml(item) {
        const role = item.role === 'user' ? 'user' : item.role === 'error' ? 'error' : 'assistant';
        const label = role === 'user' ? 'Bạn' : role === 'error' ? 'Lỗi' : 'AI';
        const time = item.createdAt ? formatDateTime(item.createdAt) : '';
        return `
            <div class="pa-ai-message is-${escapeAttr(role)}">
                <div class="pa-ai-message-meta"><strong>${escapeHtml(label)}</strong>${time ? `<span>${escapeHtml(time)}</span>` : ''}</div>
                <p>${escapeHtml(item.content || '')}</p>
            </div>
        `;
    }

    function renderPaTrainingChat() {
        syncPaWorkspaceUi();
        const panel = $('#paTrainingAiChatPanel');
        const log = $('#paTrainingChatLog');
        const input = $('#paTrainingChatInput');
        const sendBtn = $('#paTrainingChatSendBtn');
        if (!panel || !log) return;
        const zone = paTrainingSelectedZone();
        const trade = paTrainingSelectedTrade();
        const context = paTrainingChatContext(zone, trade);
        const scopeKey = paTrainingChatScopeKey(context);
        ensurePaTrainingChatHistoryLoaded();
        panel.classList.toggle('is-disabled', false);
        if (input) input.disabled = state.paTrainingChatLoading;
        if (sendBtn) sendBtn.disabled = state.paTrainingChatLoading;

        const messages = paTrainingVisibleChatMessages(context).slice(-40);
        const activeSymbol = trade?.symbol || zone?.symbol || state.activeMarketData?.symbol || state.paTrainingSymbol || 'chart hiện tại';
        const activeTimeframe = trade ? (state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4') : (zone?.timeframe || state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4');
        const empty = trade
            ? `Đang xem ${paTrainingCaseLabel(trade)}. Hỏi AI về vùng H4, setup vào lệnh, SL/TP hoặc bấm Đúng/Bỏ/Sửa để dạy ngay ca này.`
            : zone
              ? `Đang xem ${zone.symbol} ${zone.timeframe}. Có thể hỏi AI về vùng này, vẽ entry/SL/TP hoặc khoanh thêm vùng trên chart để dạy trọn ca.`
              : `Mình đang nghe theo chart ${activeSymbol} ${activeTimeframe}. Bạn cứ chat như ChatGPT: hỏi vùng H4, setup Price Action, hoặc bảo AI vẽ/bật chỉ báo trên biểu đồ.`;
        log.innerHTML = messages.length
            ? messages.map(paTrainingChatMessageHtml).join('')
            : `<div class="pa-ai-message is-assistant"><div class="pa-ai-message-meta"><strong>AI</strong></div><p>${escapeHtml(empty)}</p></div>`;
        if (state.paTrainingChatHistoryLoadingKey === scopeKey && !messages.length) {
            log.insertAdjacentHTML('beforeend', '<div class="pa-ai-message is-assistant is-loading"><div class="pa-ai-message-meta"><strong>AI</strong></div><p>Đang tải lại lịch sử hội thoại...</p></div>');
        }
        if (state.paTrainingChatLoading) {
            log.insertAdjacentHTML('beforeend', '<div class="pa-ai-message is-assistant is-loading"><div class="pa-ai-message-meta"><strong>AI</strong></div><p>Đang gọi OpenAI và xử lý yêu cầu...</p></div>');
        }
        if (state.paTrainingChatError) {
            log.insertAdjacentHTML('beforeend', `<div class="pa-ai-message is-error"><div class="pa-ai-message-meta"><strong>Lỗi</strong></div><p>${escapeHtml(state.paTrainingChatError)}</p></div>`);
        }
        requestAnimationFrame(() => {
            log.scrollTop = log.scrollHeight;
            log.lastElementChild?.scrollIntoView({ block: 'end', behavior: 'auto' });
            setTimeout(() => {
                if (log.isConnected) log.scrollTop = log.scrollHeight;
            }, 0);
        });
    }

    async function sendPaTrainingChat(messageOverride = '') {
        const input = $('#paTrainingChatInput');
        const message = String(messageOverride || input?.value || '').trim();
        const zone = paTrainingSelectedZone();
        const trade = paTrainingSelectedTrade();
        if (!message) return;
        if (input && !messageOverride) input.value = '';
        state.paTrainingChatError = '';
        const historyBeforeSend = state.paTrainingChatMessages.slice(-12);
        state.paTrainingChatLoading = true;
        const tradeSnapshot = paTrainingTradeSnapshot(trade);
        const chartAttachment = paTrainingChartQuestionAttachment(isPaTrainingDrawingRequest(message));
        const chatContext = paTrainingChatContext(zone, trade, tradeSnapshot);
        const scopeKey = paTrainingChatScopeKey(chatContext);
        state.paTrainingChatRevision += 1;
        state.paTrainingChatLoadedKey = scopeKey;
        const userMessage = normalizePaTrainingChatMessages([{
            role: 'user',
            content: message,
            zoneId: zone?.id || '',
            tradeId: trade?.id || '',
            context: chatContext,
            symbol: chatContext.symbol,
            timeframe: chatContext.timeframe,
            createdAt: new Date().toISOString(),
        }], chatContext)[0];
        if (userMessage) {
            state.paTrainingChatMessages.push(userMessage);
            persistPaTrainingChatMessages(scopeKey, state.paTrainingChatMessages, chatContext);
        }
        renderPaTrainingChat();
        try {
            const data = await api('pa_training_ai_chat', {
                message,
                zone: zone || null,
                trade: tradeSnapshot,
                context: chatContext,
                history: historyBeforeSend,
                chart_attachment: chartAttachment,
            });
            if (Array.isArray(data.messages)) {
                state.paTrainingChatMessages = normalizePaTrainingChatMessages(data.messages, chatContext);
            } else {
                state.paTrainingChatMessages.push({
                    id: paTrainingChatMessageId(),
                    role: 'assistant',
                    content: data.reply || 'Mình đã nhận được nhận xét của bạn.',
                    zoneId: zone?.id || '',
                    tradeId: trade?.id || '',
                    symbol: chatContext.symbol,
                    timeframe: chatContext.timeframe,
                    context: chatContext,
                    createdAt: new Date().toISOString(),
                });
            }
            persistPaTrainingChatMessages(scopeKey, state.paTrainingChatMessages, chatContext);
            if (data.pa_training_memory) {
                state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
            }
            const actions = paTrainingActionsWithLocalFallback(data.actions || [], message, historyBeforeSend);
            await executePaTrainingChatActions(actions);
        } catch (error) {
            state.paTrainingChatError = error.message;
            showToast(error.message);
        } finally {
            state.paTrainingChatLoading = false;
            renderPaTraining();
        }
    }

    function foldPaTrainingCommandText(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[đĐ]/g, 'd')
            .toLowerCase();
    }

    function isPaTrainingDrawingRequest(message) {
        const text = foldPaTrainingCommandText(message);
        return /\bve\b/.test(text) || ['draw ', 'danh dau', 'khoanh', 'ke len chart', 'to vung'].some((term) => text.includes(term));
    }

    function extractPaTrainingSymbolFromChatText(text) {
        const match = String(text || '').match(/\b([A-Z]{3})[\s_\/.-]?([A-Z]{3})\b/i);
        if (match) {
            const known = new Set(['AUD', 'CAD', 'CHF', 'EUR', 'GBP', 'JPY', 'NZD', 'USD', 'XAU', 'XAG', 'BTC', 'ETH']);
            const base = String(match[1] || '').toUpperCase();
            const quote = String(match[2] || '').toUpperCase();
            if (known.has(base) && known.has(quote)) return normalizeSymbolKey(base + quote);
        }
        const aliases = { au: 'AUDUSD', gu: 'GBPUSD', eu: 'EURUSD', uj: 'USDJPY', uc: 'USDCAD', nu: 'NZDUSD', aj: 'AUDJPY', gj: 'GBPJPY', vang: 'XAUUSD', gold: 'XAUUSD' };
        const tokens = foldPaTrainingCommandText(text).match(/[a-z0-9]+/g) || [];
        return tokens.map((token) => aliases[token]).find(Boolean) || '';
    }

    function extractPaTrainingTimeframeFromChatText(text) {
        const match = foldPaTrainingCommandText(text).match(/\b(m1|m5|m15|m30|h1|h4|d1)\b/i);
        return match ? String(match[1] || '').toUpperCase() : '';
    }

    function inferPaTrainingOpenSymbolAction(message, history = []) {
        const folded = foldPaTrainingCommandText(message);
        const wantsOpen = /\b(mo|xem|chart|bieu do|chuyen|doi|sang|open)\b/.test(folded);
        const timeframe = extractPaTrainingTimeframeFromChatText(message);
        let symbol = extractPaTrainingSymbolFromChatText(message);
        if (!symbol && wantsOpen) {
            for (let i = history.length - 1; i >= 0; i -= 1) {
                symbol = extractPaTrainingSymbolFromChatText(history[i]?.content || '');
                if (symbol) break;
            }
        }
        if (!symbol || (!wantsOpen && !timeframe)) return null;
        return { type: 'open_symbol_chart', symbol, timeframe };
    }

    function paTrainingActionsWithLocalFallback(actions, message, history = []) {
        const list = Array.isArray(actions) ? actions.filter(Boolean) : [];
        const inferred = inferPaTrainingOpenSymbolAction(message, history);
        if (!inferred) return list;
        const hasSameOpenAction = list.some((action) => action?.type === 'open_symbol_chart' && sameSymbol(action.symbol, inferred.symbol));
        if (!hasSameOpenAction) list.unshift(inferred);
        return list;
    }

    function applyPaTrainingIndicatorAction(action) {
        const indicator = String(action.indicator || '');
        if (!indicator || !(indicator in state.chartView.indicators)) return;
        const enabled = action.enabled !== false;
        state.chartView.indicators[indicator] = enabled;
        if (!state.chartView.indicatorColors || typeof state.chartView.indicatorColors !== 'object') {
            state.chartView.indicatorColors = {};
        }
        if (action.color) {
            state.chartView.indicatorColors[indicator] = action.color;
        }
        state.chartView.indicatorTemplate = 'custom';
        persistChartSettings();
        if (state.activeMarketData) {
            drawMarketChart(state.activeMarketData);
        }
        showToast(`${enabled ? 'Đã bật' : 'Đã tắt'} ${action.label || indicator} trên biểu đồ`);
    }
    async function executePaTrainingChatActions(actions) {
        for (const action of actions) {
            if (!action || !action.type) continue;
            if (action.type === 'save_case_feedback') {
                setPaTrainingSessionDecision(action.decision, action.label || action.decision, action.note || '');
            } else if (action.type === 'save_zone_feedback') {
                await savePaTrainingZoneFeedback(action.decision, action.label || action.decision, action.note || '', { advance: false, silent: true });
            } else if (action.type === 'save_current_session') {
                await savePaTrainingCurrentSession({ allowNeutral: true, note: action.note || '', advance: true });
            } else if (action.type === 'save_chat_lesson') {
                await savePaTrainingLessonFromChat();
            } else if (action.type === 'open_symbol_chart') {
                await openPaTrainingSymbolFromAi(action.symbol, action.timeframe || state.paTrainingChartTimeframe || 'H4');
            } else if (action.type === 'load_trade_cases') {
                if (action.symbol) await openPaTrainingSymbolFromAi(action.symbol, action.timeframe || 'H4', { silent: true });
                await loadPaTrainingTradeCases();
            } else if (action.type === 'start_trade_cases') {
                await startPaTrainingCaseQueue();
            } else if (action.type === 'open_chart') {
                await openPaTrainingChart(action.timeframe || 'H4');
            } else if (action.type === 'chart_boundary') {
                jumpChartToBoundary(action.where === 'start' ? 'start' : 'end');
                showToast(action.where === 'start' ? 'Đã về đầu biểu đồ' : 'Đã về cuối biểu đồ');
            } else if (action.type === 'fit_chart') {
                fitFullChart();
                showToast('Đã đưa chart về vừa khung');
            } else if (action.type === 'next_trade') {
                movePaTrainingTrade(1);
            } else if (action.type === 'previous_trade') {
                movePaTrainingTrade(-1);
            } else if (action.type === 'next_zone') {
                movePaTrainingZone(1);
            } else if (action.type === 'previous_zone') {
                movePaTrainingZone(-1);
            } else if (action.type === 'set_indicator') {
                applyPaTrainingIndicatorAction(action);
            } else if (action.type === 'draw_chart_annotation') {
                applyPaTrainingChartDrawingAction(action);
            }
        }
    }

    function applyPaTrainingChartDrawingAction(action) {
        const data = state.activeMarketData;
        const layout = state.chartView.lastLayout;
        const source = action.drawing && typeof action.drawing === 'object' ? action.drawing : null;
        if (!data || !layout || !source) {
            showToast('Chưa có biểu đồ sẵn sàng để vẽ');
            return false;
        }
        if ((action.symbol && !sameSymbol(action.symbol, data.symbol)) || (action.timeframe && String(action.timeframe).toUpperCase() !== String(data.timeframe).toUpperCase())) {
            showToast('Biểu đồ đã đổi cặp hoặc khung thời gian; hãy gửi lại yêu cầu vẽ');
            return false;
        }
        const type = String(source.type || '');
        const supported = new Set([...BOX_TOOLS, ...LINE_LIKE_TOOLS, ...CHANNEL_TOOLS, ...MULTI_POINT_TOOLS, ...ONE_CLICK_TOOLS, ...TEXT_TOOLS, 'position']);
        if (!supported.has(type) || state.chartView.drawingsLocked) {
            showToast(state.chartView.drawingsLocked ? 'Hình vẽ đang bị khóa' : 'Loại hình vẽ này chưa được hỗ trợ');
            return false;
        }
        const bars = layout.allBars || [];
        if (!bars.length) {
            showToast('Biểu đồ chưa có nến để neo hình vẽ');
            return false;
        }
        const pointFrom = (item) => {
            const barIndex = Number(item?.barIndex);
            const price = Number(item?.price);
            if (!Number.isFinite(barIndex) || !Number.isFinite(price) || price <= 0 || !bars.length || barIndex < 0 || barIndex >= bars.length) return null;
            return enrichDomainPointTime({ barIndex: Math.round(barIndex), price }, layout);
        };
        let drawing;
        if (Array.isArray(source.priceRange) && source.priceRange.length >= 2) {
            const low = Number(source.priceRange[0]);
            const high = Number(source.priceRange[1]);
            if (type !== 'rect' || !Number.isFinite(low) || !Number.isFinite(high) || low <= 0 || high <= low) return false;
            drawing = { ...source, points: [
                enrichDomainPointTime({ barIndex: clamp(Math.floor(layout.first), 0, Math.max(0, bars.length - 1)), price: high }, layout),
                enrichDomainPointTime({ barIndex: clamp(Math.ceil(layout.lastExclusive - 1), 0, Math.max(0, bars.length - 1)), price: low }, layout),
            ] };
        } else if (type === 'position') {
            const direction = source.direction === 'short' ? 'short' : 'long';
            const entry = Number(source.entry), stop = Number(source.stop), target = Number(source.target);
            if (![entry, stop, target].every((value) => Number.isFinite(value) && value > 0) || (direction === 'long' ? !(stop < entry && target > entry) : !(stop > entry && target < entry))) return false;
            const startBar = clamp(Math.round(Number(source.startBar ?? Math.floor(layout.lastExclusive - Math.max(8, (Number(layout.visibleCount) || 60) * 0.15)))), 0, Math.max(0, bars.length - 1));
            const endBar = clamp(Math.max(startBar + 1, Math.round(Number(source.endBar ?? Math.ceil(layout.lastExclusive - 1)))), startBar + 1, Math.max(startBar + 1, bars.length - 1));
            const start = enrichDomainPointTime({ barIndex: startBar, price: entry }, layout);
            const end = enrichDomainPointTime({ barIndex: endBar, price: entry }, layout);
            drawing = { ...source, type: 'position', direction, startBar, endBar, startTime: start?.time, startTimeIso: start?.time_iso, endTime: end?.time, endTimeIso: end?.time_iso };
        } else if (['hline', 'hray', 'alert'].includes(type)) {
            const price = Number(source.price);
            if (!Number.isFinite(price) || price <= 0) return false;
            drawing = { ...source, type, price };
            if (type === 'hray') drawing.startBar = clamp(Number(source.barIndex ?? Math.floor(layout.first)), 0, Math.max(0, bars.length - 1));
        } else if (type === 'vline') {
            const point = pointFrom({ barIndex: source.barIndex, price: layout.lo });
            if (!point) return false;
            drawing = { ...source, type, barIndex: point.barIndex, time: point.time, time_iso: point.time_iso, sourceTimeframe: data.timeframe };
        } else if (type === 'cross-line') {
            const point = pointFrom({ barIndex: source.barIndex, price: source.price });
            if (!point) return false;
            drawing = { ...source, type, barIndex: point.barIndex, price: point.price, time: point.time, time_iso: point.time_iso, sourceTimeframe: data.timeframe };
        } else if (TEXT_TOOLS.has(type) || ['price-label', 'dot', 'pin', 'flag', 'icon', 'arrow-marker', 'up-arrow', 'down-arrow'].includes(type)) {
            const point = pointFrom(source.point);
            if (!point) return false;
            drawing = { ...source, type, point };
            if (type === 'price-label' && !Number.isFinite(Number(drawing.price))) drawing.price = point.price;
        } else {
            const sourcePoints = Array.isArray(source.points) ? source.points : [];
            const points = sourcePoints.map(pointFrom);
            if (points.some((point) => !point)) return false;
            if (points.length < 2) return false;
            drawing = { ...source, type, points };
        }
        const safeColor = /^#[0-9a-f]{6}$/i.test(String(drawing.color || '')) ? drawing.color : defaultDrawingColor(type);
        const pendingZoneType = state.chartView.pendingZoneType;
        state.chartView.pendingZoneType = null;
        try {
            drawing = styledDrawing({ ...drawing, color: safeColor, width: clamp(Number(drawing.width || 2), 1, 6), opacity: clamp(Number(drawing.opacity ?? 16), 0, 60) });
        } finally {
            state.chartView.pendingZoneType = pendingZoneType;
        }
        if (!state.chartView.drawingsVisible) {
            state.chartView.drawingsVisible = true;
            persistChartSettings();
        }
        commitDrawing(drawing);
        showToast(`Đã vẽ ${DRAWING_TOOL_LABELS[type] || type} trên ${data.symbol} ${data.timeframe}`);
        return true;
    }

    function movePaTrainingZone(step) {
        const zones = paTrainingFilteredZones();
        if (!zones.length) return;
        const currentIndex = Math.max(0, zones.findIndex((zone) => zone.id === state.paTrainingSelectedZoneId));
        const nextIndex = (currentIndex + step + zones.length) % zones.length;
        state.paTrainingSelectedZoneId = zones[nextIndex].id;
        renderPaTraining();
        loadPaTrainingChartForSelection(state.paTrainingChartTimeframe || 'H4', { silent: true }).catch((e) => showToast(e.message));
        showToast(`Đã chuyển sang vùng ${nextIndex + 1}/${zones.length}`);
    }

    function movePaTrainingTrade(step) {
        const cases = paTrainingFilteredCases();
        if (cases.length) {
            const currentIndex = Math.max(0, cases.findIndex((item) => item.id === state.paTrainingSelectedCaseId));
            if (step > 0) {
                const currentId = cases[currentIndex]?.id;
                if (currentId && !state.paTrainingSkippedCaseIds.includes(currentId)) state.paTrainingSkippedCaseIds.push(currentId);
                persistPaWorkspace({ skippedCaseIds: state.paTrainingSkippedCaseIds });
                if (currentIndex >= cases.length - 1) {
                    loadPaTrainingTradeCases({ silent: true, keepSkipped: true, advanceAfterSave: true }).catch((error) => showToast(error.message));
                    return;
                }
            }
            const nextIndex = (currentIndex + step + cases.length) % cases.length;
            selectPaTrainingCaseById(cases[nextIndex].id, { focus: true });
            showToast(`Đã chuyển sang ca ${nextIndex + 1}/${cases.length}`);
            return;
        }
        if (state.paTrainingQueueLoaded) {
            showToast('Không còn ca tiếp theo trong dữ liệu hiện có.');
            return;
        }
        if (!state.activeMarketData) {
            movePaTrainingZone(step);
            return;
        }
        const run = activeBacktestRun(getChartAnalytics(state.activeMarketData));
        const trades = filteredTrades(run?.trades || []);
        if (!trades.length) {
            movePaTrainingZone(step);
            return;
        }
        const currentIndex = Math.max(0, trades.findIndex((trade) => trade.id === state.chartView.selectedTradeId));
        const nextIndex = (currentIndex + step + trades.length) % trades.length;
        focusTrade(trades[nextIndex]);
        renderPaTraining();
        showToast(`Đã chuyển sang ca ${nextIndex + 1}/${trades.length}`);
    }

    function skipPaTrainingCurrentCase() {
        const cases = paTrainingFilteredCases();
        if (cases.length) movePaTrainingTrade(1);
    }

    function activatePaTrainingLevelDrag() {
        if (!paTrainingSelectedTeachingCase()) {
            showToast('Chọn một ca trade trước rồi kéo các đường trên chart.');
            return;
        }
        setChartTool('cursor');
        showToast('Kéo trực tiếp đường Entry, SL hoặc TP trên chart; thả chuột để áp dụng.');
        $('#marketChart')?.focus({preventScroll: true});
    }
    function renderPaTrainingSetupGuide() {
        const target = $('#paTrainingSetupGuide');
        if (!target) return;
        const accepted = (state.paTrainingMemory?.zone_reviews || []).filter((item) => item.decision === 'trade_zone');
        if (!accepted.length) {
            target.innerHTML = `
                <div class="notice">
                    Chưa có vùng H4 nào được chọn để trade. Hãy chấm trước khoảng 50-100 vùng H4, sau đó mình sẽ bật bước lọc setup M15 quanh các vùng đó.
                </div>
            `;
            return;
        }
        target.innerHTML = `
            <div class="pa-training-stats">
                ${paTrainingStatCard('Vùng đủ điều kiện', number(accepted.length), 'Nguồn để sinh setup M15')}
                ${paTrainingStatCard('Việc tiếp theo', 'M15', 'Tìm nến đảo chiều, entry, SL, TP')}
                ${paTrainingStatCard('Trạng thái', 'Sẵn sàng bước 2', 'Có thể bắt đầu tạo hàng đợi setup')}
            </div>
            <div class="pa-training-recent">
                ${accepted.slice(-8).reverse().map((item) => `
                    <div class="pa-feedback-row">
                        <strong>${escapeHtml(item.symbol || '')} ${escapeHtml(item.timeframe || '')} | ${escapeHtml(item.label || item.decision)}</strong>
                        <span>${escapeHtml(item.note || 'Chưa có ghi chú')}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderPaTrainingRecentFeedback() {
        const target = $('#paTrainingRecentFeedback');
        if (!target) return;
        const memory = normalizePaTrainingMemory(state.paTrainingMemory);
        const lessonRows = memory.lessons.map((item) => ({
            ...item,
            kindLabel: 'Bài học PA',
            label: item.title || 'Bài học',
            note: item.summary || item.appliesWhen || '',
        }));
        const rows = [
            ...memory.zone_reviews.map((item) => ({ ...item, kindLabel: 'Vùng H4' })),
            ...memory.setup_reviews.map((item) => ({ ...item, kindLabel: 'Ca trade' })),
            ...lessonRows,
        ]
            .sort((a, b) => String(b.createdAt || '').localeCompare(String(a.createdAt || '')))
            .slice(0, 14);
        if (!rows.length) {
            target.innerHTML = '<div class="notice">Chưa có quyết định/bài học nào. Khi bạn chat, chấm ca hoặc bấm Học, bộ nhớ sẽ hiện ở đây.</div>';
            return;
        }
        target.innerHTML = rows.map((item) => `
            <div class="pa-feedback-row ${item.kindLabel === 'Bài học PA' ? 'is-lesson' : ''}">
                <strong>${escapeHtml(item.kindLabel)} | ${escapeHtml(item.symbol || '')} ${escapeHtml(item.timeframe || '')} | ${escapeHtml(item.label || item.decision || '')}</strong>
                <span>${escapeHtml(formatDateTime(item.createdAt))}${item.note ? ` | ${escapeHtml(item.note)}` : ''}</span>
            </div>
        `).join('');
    }

    function paJournalSnapshot(review) {
        const snapshot = review?.snapshot && typeof review.snapshot === 'object' ? review.snapshot : {};
        const summary = snapshot.summary && typeof snapshot.summary === 'object' ? snapshot.summary : {};
        return {
            trade: snapshot.trade || summary.trade || (snapshot.direction ? snapshot : {}),
            zone: snapshot.zone || summary.zone || null,
        };
    }

    function paJournalTimeValue(value) {
        const parsed = Date.parse(String(value || ''));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function paJournalDateLabel(value) {
        return value ? formatDateTime(value) : '—';
    }

    function paJournalWithinPeriod(value) {
        if (state.paJournalPeriod === 'all') return true;
        const timestamp = paJournalTimeValue(value);
        if (!timestamp) return true;
        const days = Math.max(1, Number(state.paJournalPeriod) || 30);
        return timestamp >= Date.now() - days * 86400000;
    }

    function paJournalSearchMatches(item, values) {
        const query = String(state.paJournalSearch || '').trim().toLocaleLowerCase('vi');
        if (!query) return true;
        return values.join(' ').toLocaleLowerCase('vi').includes(query);
    }

    function paJournalTradeRows() {
        const memory = normalizePaTrainingMemory(state.paTrainingMemory);
        const lessonByReview = new Map(memory.lessons.filter((lesson) => lesson.sourceReviewId).map((lesson) => [lesson.sourceReviewId, lesson]));
        return memory.setup_reviews.map((review) => {
            const snapshot = paJournalSnapshot(review);
            const trade = snapshot.trade || {};
            const zone = snapshot.zone || {};
            const lesson = lessonByReview.get(review.id) || null;
            const symbol = review.symbol || trade.symbol || zone.symbol || '';
            const contextTf = trade.contextTimeframe || zone.timeframe || '';
            const tradeTf = trade.timeframe || review.timeframe || '';
            const date = trade.entryTime || trade.signalTime || review.createdAt || '';
            const lessonText = lesson?.content || lesson?.summary || lesson?.appliesWhen || review.note || trade.entryReason || trade.exitReason || trade.reason || '';
            return { id: review.id, review, trade, zone, lesson, symbol, contextTf, tradeTf, date, lessonText };
        }).filter((item) => paJournalWithinPeriod(item.date))
            .filter((item) => paJournalSearchMatches(item, [item.symbol, item.contextTf, item.tradeTf, item.review.label, item.review.decision, item.review.note, item.lessonText, item.trade.direction, item.trade.exitType]))
            .sort((a, b) => paJournalTimeValue(b.date) - paJournalTimeValue(a.date));
    }

    function paJournalLessonRows() {
        const memory = normalizePaTrainingMemory(state.paTrainingMemory);
        return memory.lessons.filter((lesson) => paJournalWithinPeriod(lesson.createdAt))
            .filter((lesson) => paJournalSearchMatches(lesson, [lesson.title, lesson.content, lesson.summary, lesson.appliesWhen, lesson.symbol, lesson.timeframe, ...(Array.isArray(lesson.tags) ? lesson.tags : [])]))
            .sort((a, b) => paJournalTimeValue(b.createdAt) - paJournalTimeValue(a.createdAt));
    }

    function paJournalRuleGroups() {
        const groups = new Map();
        paJournalLessonRows().forEach((lesson) => {
            const content = String(lesson.content || lesson.summary || lesson.appliesWhen || '').trim();
            const title = String(lesson.title || 'Bài học PA').trim();
            const key = content.toLocaleLowerCase('vi').replace(/[^\p{L}\p{N}]+/gu, ' ').trim() || `${title.toLocaleLowerCase('vi')} ${lesson.id || ''}`;
            if (!groups.has(key)) groups.set(key, { id: `rule:${key}`, title, content, lessons: [], sources: new Set(), timeframes: new Set(), createdAt: lesson.createdAt || '' });
            const group = groups.get(key);
            group.lessons.push(lesson);
            if (lesson.symbol) group.sources.add(String(lesson.symbol).toUpperCase());
            if (lesson.timeframe) group.timeframes.add(String(lesson.timeframe).toUpperCase());
            if (paJournalTimeValue(lesson.createdAt) > paJournalTimeValue(group.createdAt)) group.createdAt = lesson.createdAt;
        });
        return [...groups.values()].filter((rule) => paJournalSearchMatches(rule, [rule.title, rule.content, ...rule.sources, ...rule.timeframes]))
            .sort((a, b) => paJournalTimeValue(b.createdAt) - paJournalTimeValue(a.createdAt));
    }

    function paJournalDecisionLabel(review) {
        const labels = {
            good_case: 'Đúng cách', bad_case: 'Bỏ ca', weak_case: 'Cần sửa', adjusted_case: 'Đã chỉnh', session_note: 'Đã ghi chú',
            approved: 'Đúng kế hoạch', edited: 'Đã sửa', rejected: 'Không phù hợp',
        };
        return review.label || labels[review.decision] || review.decision || 'Đã lưu';
    }

    function paJournalDecisionTone(review) {
        if (['good_case', 'approved'].includes(review.decision)) return 'is-good';
        if (['bad_case', 'rejected'].includes(review.decision)) return 'is-bad';
        if (['weak_case', 'edited', 'adjusted_case'].includes(review.decision)) return 'is-review';
        return '';
    }

    function paJournalResult(trade) {
        if (trade.netR !== null && trade.netR !== undefined && trade.netR !== '' && Number.isFinite(Number(trade.netR))) {
            const value = Number(trade.netR);
            return { text: `${formatSigned(value, 2)}R`, className: value > 0 ? 'is-win' : value < 0 ? 'is-loss' : 'is-flat' };
        }
        return { text: trade.exitType ? displayExitType(trade.exitType) : 'Chưa có kết quả', className: 'is-neutral' };
    }

    function paJournalSide(trade) {
        const direction = String(trade.direction || '').toLowerCase();
        if (!direction) return '—';
        return displayTradeSide(direction);
    }

    function paJournalTimeframe(item) {
        const values = [...new Set([item.contextTf, item.tradeTf].filter(Boolean).map((value) => String(value).toUpperCase()))];
        return values.length ? values.join(' / ') : '—';
    }

    function paJournalRelatedLessons(entry) {
        const memory = normalizePaTrainingMemory(state.paTrainingMemory);
        const reviewText = [entry.review.note, entry.trade.reason, entry.trade.zoneReason, entry.trade.entryReason, entry.trade.exitReason, entry.trade.direction].join(' ').toLocaleLowerCase('vi');
        const terms = [...new Set(reviewText.match(/[\p{L}\p{N}]{3,}/gu) || [])];
        return memory.lessons.map((lesson) => {
            const content = [lesson.title, lesson.content, lesson.summary, lesson.appliesWhen, ...(Array.isArray(lesson.tags) ? lesson.tags : [])].join(' ').toLocaleLowerCase('vi');
            const sameSource = lesson.sourceReviewId === entry.review.id;
            let score = Math.max(0, Math.min(5, Number(lesson.confidence) || 1));
            if (sameSource) score += 30;
            if (entry.symbol && String(lesson.symbol || '').toUpperCase() === String(entry.symbol).toUpperCase()) score += 16;
            if (entry.tradeTf && String(lesson.timeframe || '').toUpperCase() === String(entry.tradeTf).toUpperCase()) score += 8;
            score += terms.reduce((total, term) => total + (content.includes(term) ? 3 : 0), 0);
            score += Math.min(6, Math.floor(Math.log2(Math.max(1, Number(lesson.support_count) || 1)) * 2));
            return { lesson, score };
        }).sort((a, b) => b.score - a.score || paJournalTimeValue(b.lesson.createdAt) - paJournalTimeValue(a.lesson.createdAt)).slice(0, 3).map((item) => item.lesson);
    }

    function paJournalPrice(value) {
        if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return '—';
        return Number(value).toLocaleString('vi-VN', { maximumFractionDigits: 6 });
    }

    function paJournalTradeDetail(entry) {
        const result = paJournalResult(entry.trade);
        const lesson = entry.lessonText || 'Ca này chưa có ghi chú bài học. M có thể mở lại ca trong Dạy Price Action để bổ sung nhận xét.';
        const related = paJournalRelatedLessons(entry);
        const prices = [
            ['Entry', entry.trade.entryPrice], ['SL', entry.trade.initialStop], ['TP', entry.trade.targetPrice], ['Thoát', entry.trade.exitPrice],
        ].filter(([, value]) => value !== null && value !== undefined && value !== '');
        return `
            <div class="pa-journal-detail-heading">
                <div><h2>Chi tiết ca trade</h2><p>${escapeHtml(entry.symbol || '—')} <span>·</span> ${escapeHtml(paJournalSide(entry.trade))} <span>·</span> ${escapeHtml(paJournalTimeframe(entry))} <span>·</span> ${escapeHtml(paJournalDateLabel(entry.date))}</p></div>
                <span class="pa-journal-result ${result.className}">${escapeHtml(result.text)}</span>
            </div>
            <div class="pa-journal-detail-columns">
                <section class="pa-journal-learning">
                    <h3><span aria-hidden="true">✳</span> Bài học đã lưu sau ca trade</h3>
                    <p class="pa-journal-learning-text">${escapeHtml(lesson)}</p>
                    ${prices.length ? `<dl class="pa-journal-prices">${prices.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(paJournalPrice(value))}</dd></div>`).join('')}</dl>` : ''}
                    ${entry.lesson?.tags?.length ? `<div class="pa-journal-tags">${entry.lesson.tags.map((tag) => `<span>${escapeHtml(tag)}</span>`).join('')}</div>` : ''}
                </section>
                <section class="pa-journal-rules">
                    <div class="pa-journal-subhead"><h3>Quy tắc AI có thể tham khảo tiếp</h3><span>Chọn theo mức liên quan</span></div>
                    ${related.length ? `<ol>${related.map((item) => `<li><p>${escapeHtml(item.content || item.summary || item.appliesWhen || item.title || 'Bài học PA')}</p><small>${escapeHtml([item.symbol, item.timeframe].filter(Boolean).join(' · ') || 'Chưa gắn cặp/khung')}</small></li>`).join('')}</ol>` : '<p class="pa-journal-empty-inline">Chưa có bài học phù hợp trong bộ nhớ. Lưu ghi chú khi dạy ca để AI có thêm kinh nghiệm tham khảo.</p>'}
                    <div class="pa-journal-rule-footnote">AI dùng bài học phù hợp khi chat hoặc phân tích ca; hiện chưa tự đặt lệnh thật.</div>
                </section>
            </div>`;
    }

    function paJournalLessonDetail(lesson, isRule = false) {
        if (!lesson) return '<div class="pa-journal-detail-empty">Chọn một bài học để xem nội dung.</div>';
        const lessons = isRule ? lesson.lessons : [lesson];
        const content = lesson.content || lesson.summary || lesson.appliesWhen || 'Bài học chưa có nội dung.';
        const sources = [...new Set(lessons.map((item) => item.symbol).filter(Boolean).map((value) => String(value).toUpperCase()))];
        const timeframes = [...new Set(lessons.map((item) => item.timeframe).filter(Boolean).map((value) => String(value).toUpperCase()))];
        const date = lesson.createdAt;
        return `
            <div class="pa-journal-detail-heading">
                <div><h2>${isRule ? 'Chi tiết quy tắc' : 'Chi tiết bài học'}</h2><p>${escapeHtml(lesson.title || 'Bài học Price Action')} <span>·</span> ${escapeHtml(paJournalDateLabel(date))}</p></div>
                <span class="pa-journal-memory-status">Đã lưu trong bộ nhớ</span>
            </div>
            <div class="pa-journal-detail-columns">
                <section class="pa-journal-learning"><h3>Nội dung bài học</h3><p class="pa-journal-learning-text">${escapeHtml(content)}</p></section>
                <section class="pa-journal-rules"><h3>Phạm vi ghi nhận</h3><p class="pa-journal-source-list">${escapeHtml(sources.join(', ') || 'Chưa gắn cặp cụ thể')} <span>·</span> ${escapeHtml(timeframes.join(', ') || 'Chưa gắn khung')}</p><p class="pa-journal-rule-footnote">AI xếp bài học theo độ liên quan với cặp, khung và nội dung đang phân tích. Mức giá/vùng được ghi trong bài là ví dụ của dữ liệu nguồn.</p><p class="pa-journal-rule-count">${lessons.length} lần ghi nhận <span>·</span> Cập nhật ${escapeHtml(paJournalDateLabel(date))}</p></section>
            </div>`;
    }

    function paJournalPageRows(items, pageSize = 50) {
        const pageCount = Math.max(1, Math.ceil(items.length / pageSize));
        state.paJournalPage = Math.max(0, Math.min(state.paJournalPage || 0, pageCount - 1));
        return { pageCount, rows: items.slice(state.paJournalPage * pageSize, (state.paJournalPage + 1) * pageSize) };
    }

    function paJournalPagination(total, pageCount) {
        if (total <= 50) return '';
        const page = state.paJournalPage || 0;
        const first = page * 50 + 1;
        const last = Math.min(total, first + 49);
        return `<div class="pa-journal-pagination"><span>${first}–${last} / ${total}</span><div><button class="button secondary" type="button" data-pa-journal-page="prev" ${page <= 0 ? 'disabled' : ''} aria-label="Trang trước">‹</button><button class="button secondary" type="button" data-pa-journal-page="next" ${page >= pageCount - 1 ? 'disabled' : ''} aria-label="Trang sau">›</button></div></div>`;
    }

    function renderPaJournal() {
        const tableRegion = $('#paJournalTableRegion');
        const detailRegion = $('#paJournalDetail');
        if (!tableRegion || !detailRegion) return;
        const view = state.paJournalView || 'trades';
        const allItems = view === 'trades' ? paJournalTradeRows() : view === 'lessons' ? paJournalLessonRows() : paJournalRuleGroups();
        const paged = paJournalPageRows(allItems);
        const visibleItems = paged.rows;
        const count = $('#paJournalCount');
        if (count) count.textContent = `${allItems.length.toLocaleString('vi-VN')} mục`;
        $$('[data-pa-journal-view]').forEach((button) => {
            const active = button.dataset.paJournalView === view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.tabIndex = active ? 0 : -1;
        });
        if (!allItems.some((item) => item.id === state.paJournalSelectedId) || !visibleItems.some((item) => item.id === state.paJournalSelectedId)) {
            state.paJournalSelectedId = visibleItems[0]?.id || '';
        }
        const selected = visibleItems.find((item) => item.id === state.paJournalSelectedId) || null;

        if (!allItems.length) {
            const empty = view === 'trades' ? 'Chưa có ca trade nào được lưu trong khoảng thời gian này.' : view === 'lessons' ? 'Chưa có bài học AI nào trong khoảng thời gian này.' : 'Chưa có quy tắc nào được lưu trong khoảng thời gian này.';
            tableRegion.innerHTML = `<div class="pa-journal-empty"><strong>${escapeHtml(empty)}</strong><span>Thử đổi khoảng thời gian hoặc lưu ca/bài học trong mục Dạy Price Action.</span></div>`;
            detailRegion.innerHTML = '<div class="pa-journal-detail-empty">Chi tiết sẽ hiện ở đây khi có dữ liệu.</div>';
            return;
        }

        let tableHead = '';
        let tableBody = '';
        if (view === 'trades') {
            tableHead = '<th scope="col">Thời gian</th><th scope="col">Cặp</th><th scope="col">Khung</th><th scope="col">Lệnh</th><th scope="col">Kết quả</th><th scope="col">Đánh giá</th><th scope="col">Bài học</th>';
            tableBody = visibleItems.map((item) => {
                const result = paJournalResult(item.trade);
                return `<tr data-pa-journal-id="${escapeAttr(item.id)}" tabindex="0" aria-selected="${item.id === state.paJournalSelectedId ? 'true' : 'false'}" class="${item.id === state.paJournalSelectedId ? 'is-selected' : ''}"><td>${escapeHtml(paJournalDateLabel(item.date))}</td><td class="pa-journal-symbol">${escapeHtml(item.symbol || '—')}</td><td>${escapeHtml(paJournalTimeframe(item))}</td><td>${escapeHtml(paJournalSide(item.trade))}</td><td><span class="pa-journal-result ${result.className}">${escapeHtml(result.text)}</span></td><td><span class="pa-journal-decision ${paJournalDecisionTone(item.review)}">${escapeHtml(paJournalDecisionLabel(item.review))}</span></td><td class="pa-journal-lesson-cell">${escapeHtml(item.lessonText || 'Chưa có ghi chú')}</td></tr>`;
            }).join('');
        } else if (view === 'lessons') {
            tableHead = '<th scope="col">Thời gian</th><th scope="col">Bài học</th><th scope="col">Cặp ghi nhận</th><th scope="col">Khung</th><th scope="col">Nguồn</th>';
            tableBody = visibleItems.map((item) => `<tr data-pa-journal-id="${escapeAttr(item.id)}" tabindex="0" aria-selected="${item.id === state.paJournalSelectedId ? 'true' : 'false'}" class="${item.id === state.paJournalSelectedId ? 'is-selected' : ''}"><td>${escapeHtml(paJournalDateLabel(item.createdAt))}</td><td class="pa-journal-lesson-cell">${escapeHtml(item.content || item.summary || item.appliesWhen || item.title || 'Bài học PA')}</td><td class="pa-journal-symbol">${escapeHtml(item.symbol || 'Chung')}</td><td>${escapeHtml(item.timeframe || '—')}</td><td>${escapeHtml(item.sourceReviewId ? 'Ca trade' : item.kind === 'chat_lesson' ? 'Chat AI' : 'Đã lưu')}</td></tr>`).join('');
        } else {
            tableHead = '<th scope="col">Quy tắc</th><th scope="col">Cặp ghi nhận</th><th scope="col">Khung</th><th scope="col">Số lần ghi nhận</th><th scope="col">Trạng thái</th>';
            tableBody = visibleItems.map((item) => `<tr data-pa-journal-id="${escapeAttr(item.id)}" tabindex="0" aria-selected="${item.id === state.paJournalSelectedId ? 'true' : 'false'}" class="${item.id === state.paJournalSelectedId ? 'is-selected' : ''}"><td class="pa-journal-lesson-cell">${escapeHtml(item.content || item.title)}</td><td class="pa-journal-symbol">${escapeHtml([...item.sources].join(', ') || 'Chung')}</td><td>${escapeHtml([...item.timeframes].join(', ') || '—')}</td><td>${item.lessons.length}</td><td><span class="pa-journal-memory-status">Đã lưu</span></td></tr>`).join('');
        }
        tableRegion.innerHTML = `<div class="pa-journal-table-wrap"><table class="pa-journal-table"><thead><tr>${tableHead}</tr></thead><tbody>${tableBody}</tbody></table></div>${paJournalPagination(allItems.length, paged.pageCount)}`;
        if (!selected) detailRegion.innerHTML = '<div class="pa-journal-detail-empty">Chọn một mục để xem chi tiết.</div>';
        else if (view === 'trades') detailRegion.innerHTML = paJournalTradeDetail(selected);
        else detailRegion.innerHTML = paJournalLessonDetail(selected, view === 'rules');
    }

    async function loadPaJournalMemory(options = {}) {
        const data = await api('pa_training_memory', {});
        state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
        renderPaJournal();
        if (!options.silent) showToast('Đã tải nhật ký Trade & AI');
    }

    function exportPaJournal() {
        const view = state.paJournalView || 'trades';
        const items = view === 'trades' ? paJournalTradeRows() : view === 'lessons' ? paJournalLessonRows() : paJournalRuleGroups();
        if (!items.length) {
            showToast('Không có dữ liệu để xuất trong bộ lọc hiện tại.');
            return;
        }
        const rows = view === 'trades'
            ? [['Thời gian', 'Cặp', 'Khung', 'Lệnh', 'Kết quả', 'Đánh giá', 'Bài học'], ...items.map((item) => [paJournalDateLabel(item.date), item.symbol, paJournalTimeframe(item), paJournalSide(item.trade), paJournalResult(item.trade).text, paJournalDecisionLabel(item.review), item.lessonText])]
            : view === 'lessons'
                ? [['Thời gian', 'Bài học', 'Cặp ghi nhận', 'Khung', 'Nguồn'], ...items.map((item) => [paJournalDateLabel(item.createdAt), item.content || item.summary || item.appliesWhen || item.title, item.symbol || 'Chung', item.timeframe || '', item.sourceReviewId ? 'Ca trade' : item.kind || 'Đã lưu'])]
                : [['Quy tắc', 'Cặp ghi nhận', 'Khung', 'Số lần ghi nhận'], ...items.map((item) => [item.content || item.title, [...item.sources].join(', ') || 'Chung', [...item.timeframes].join(', '), item.lessons.length])];
        const csv = '\ufeff' + rows.map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `namtrade-${view}-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
    }

    async function loadPaTrainingTradeCases(options = {}) {
        const symbol = $('#paTrainingSymbolSelect')?.value || state.paTrainingSymbol;
        if (!symbol) {
            showToast('Chưa có cặp tiền có dữ liệu H4/M15 để dạy Price Action.');
            return;
        }
        state.paTrainingSymbol = symbol;
        state.paTrainingTimeframe = $('#paTrainingTimeframeSelect')?.value || 'H4';
        if (!options.keepSkipped && !options.advanceAfterSave && !options.restoreProgress) state.paTrainingSkippedCaseIds = [];
        const rememberedProgress = savedPaWorkspace(state.paTrainingSymbol, state.paTrainingTimeframe);
        state.paTrainingLoading = true;
        state.paTrainingCaseLoading = true;
        state.paTrainingError = '';
        state.paTrainingCaseError = '';
        renderPaTraining();
        try {
            const data = await api('pa_training_trade_cases', {
                symbol: state.paTrainingSymbol,
                timeframe: state.paTrainingTimeframe,
                filter: state.paTrainingZoneFilter || 'active',
                limit: clamp(Number($('#paTrainingCaseCount')?.value || 1), 1, 100),
                excluded_case_ids: state.paTrainingSkippedCaseIds,
                preferred_case_id: options.restoreProgress ? rememberedProgress.selectedCaseId || '' : options.keepSelectionCaseId || '',
            });
            state.paTrainingZones = data.zone_candidates || [];
            state.paTrainingTradeCases = data.trade_cases || [];
            state.paTrainingQueueLoaded = true;
            const remembered = savedPaWorkspace(state.paTrainingSymbol, state.paTrainingTimeframe);
            const restoreId = options.keepSelectionCaseId || (options.restoreProgress ? (state.paTrainingSelectedCaseId || remembered.selectedCaseId) : '');
            const rememberedCase = state.paTrainingTradeCases.find((item) => item.id === restoreId);
            const preferred = options.keepSelectionZoneId
                ? state.paTrainingTradeCases.find((item) => item.zoneId === options.keepSelectionZoneId)
                : null;
            state.paTrainingSelectedCaseId = options.advanceAfterSave
                ? (data.selected_case_id || '')
                : (rememberedCase?.id || preferred?.id || data.selected_case_id || state.paTrainingTradeCases[0]?.id || '');
            const selected = paTrainingSelectedTeachingCase();
            state.paTrainingSelectedZoneId = selected?.zoneId || selected?.zone?.id || state.paTrainingZones[0]?.id || '';
            state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
            if (selected) {
                await focusPaTrainingSelectedCase(options.chartTimeframe || 'M15');
                // Reloading the case queue (including after creating new cases) should focus
                // the selected setup. Restore the old replay cursor only when resuming the
                // workspace after a page/app restart; otherwise a stale cursor can hide the
                // selected case behind a replay cutoff near the beginning of the chart.
                if (options.restoreProgress === true && rememberedProgress.selectedCaseId === selected.id) restorePaTrainingReplayProgress(state.activeMarketData, rememberedProgress);
            }
            persistPaWorkspace({
                selectedCaseId: state.paTrainingSelectedCaseId,
                replayIndex: state.chartView.replay.enabled ? state.chartView.replay.index : null,
                replayEnabled: state.chartView.replay.enabled,
            });
            if (!options.silent) {
                const summary = data.case_summary || {};
                showToast(`Đã tìm ${number(state.paTrainingTradeCases.length)} lệnh đã vào và chạm SL/TP từ ${number(summary.h4_bars || 0)} nến H4, ${number(summary.m15_bars || 0)} nến M15`);
            }
        } catch (error) {
            state.paTrainingError = error.message;
            state.paTrainingCaseError = error.message;
            showToast(error.message);
        } finally {
            state.paTrainingLoading = false;
            state.paTrainingCaseLoading = false;
            renderPaTraining();
            if (options.restoreProgress === true && state.activeMarketData && rememberedProgress.selectedCaseId === state.paTrainingSelectedCaseId) {
                restorePaTrainingReplayProgress(state.activeMarketData, rememberedProgress);
                persistPaWorkspace({
                    selectedCaseId: state.paTrainingSelectedCaseId,
                    replayIndex: rememberedProgress.replayIndex ?? (state.chartView.replay.enabled ? state.chartView.replay.index : null),
                    replayEnabled: rememberedProgress.replayIndex !== null && rememberedProgress.replayIndex !== undefined ? rememberedProgress.replayEnabled !== false : state.chartView.replay.enabled,
                });
            }
        }
    }

    async function loadPaTrainingZones(options = {}) {
        return loadPaTrainingTradeCases(options);
    }
    async function loadPaTrainingMemory() {
        const data = await api('pa_training_memory', {});
        state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
        renderPaTraining();
        showToast('Đã tải lại bộ nhớ Price Action');
    }

    async function savePaTrainingZoneDecision(decision, label) {
        const note = $('#paTrainingZoneNote')?.value || '';
        await savePaTrainingZoneFeedback(decision, label, note, { advance: true });
    }

        async function savePaTrainingZoneFeedback(decision, label, note = '', options = {}) {
        const zone = paTrainingSelectedZone();
        if (!zone) return;
        const data = await api('pa_training_feedback_save', {
            kind: 'zone',
            targetId: zone.id,
            symbol: zone.symbol,
            timeframe: zone.timeframe,
            decision,
            label,
            note,
            snapshot: zone,
        });
        state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
        const index = state.paTrainingZones.findIndex((item) => item.id === zone.id);
        if (index >= 0) state.paTrainingZones[index] = { ...state.paTrainingZones[index], review: data.review };
        if (options.advance !== false) {
            const next = state.paTrainingZones.slice(index + 1).find((item) => !item.review) || state.paTrainingZones.find((item) => !item.review);
            if (next) state.paTrainingSelectedZoneId = next.id;
        }
        await loadPaTrainingChartForSelection(state.paTrainingChartTimeframe || 'H4', { silent: true });
        renderPaTraining();
        if (!options.silent) showToast(`Đã lưu: ${label}`);
    }

    async function savePaTrainingCaseDecision(decision, label) {
        if (decision === 'weak_case') { paCaseManager?.edit(); return; }
        setPaTrainingSessionDecision(decision, label);
    }

    async function savePaTrainingTradeFeedback(decision, label, note = '', options = {}) {
        const trade = paTrainingSelectedTrade();
        const zone = paTrainingSelectedZone();
        if (!trade) {
            const zoneDecision = decision === 'good_case' ? 'trade_zone' : decision === 'bad_case' ? 'skip_zone' : 'weak_zone';
            await savePaTrainingZoneFeedback(zoneDecision, label, note, options);
            return;
        }
        const tradeSnapshot = paTrainingTradeSnapshot(trade);
        const snapshot = {
            zone: zone || trade.zone || null,
            trade: tradeSnapshot,
        };
        const data = await api('pa_training_feedback_save', {
            kind: 'setup',
            targetId: trade.id || `${trade.symbol || 'trade'}_${trade.entryTime || Date.now()}`,
            symbol: trade.symbol || state.activeMarketData?.symbol || state.paTrainingSymbol || '',
            timeframe: trade.timeframe || state.activeMarketData?.timeframe || state.paTrainingChartTimeframe || 'H4',
            decision,
            label,
            note,
            snapshot,
        });
        state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
        const caseIndex = (state.paTrainingTradeCases || []).findIndex((item) => item.id === trade.id);
        if (caseIndex >= 0) {
            state.paTrainingTradeCases[caseIndex] = { ...state.paTrainingTradeCases[caseIndex], review: data.review, caseStatus: 'reviewed' };
        }
        if (options.advance !== false) await loadPaTrainingTradeCases({ silent: true, advanceAfterSave: true });
        else renderPaTraining();
        if (!options.silent) showToast(`Đã lưu ca trade: ${label}`);
    }
    async function openPaTrainingChart(timeframe) {
        const teachingCase = paTrainingSelectedTeachingCase();
        const zone = paTrainingSelectedZone();
        const symbol = teachingCase?.symbol || zone?.symbol || state.activeMarketData?.symbol || $('#paTrainingSymbolSelect')?.value || state.paTrainingSymbol;
        const targetTimeframe = timeframe || 'H4';
        if (!symbol) {
            showToast('Chọn cặp tiền trước khi mở chart.');
            return;
        }
        const opened = await loadPaTrainingChartForSymbol(symbol, targetTimeframe, {
            anchorTime: teachingCase?.anchorTime || teachingCase?.entryTime || zone?.firstTouchTime || zone?.originCandleTime || zone?.createdAt,
        });
        if (opened) {
            const active = state.activeMarketData;
            showToast(`Đã mở ${active?.symbol || symbol} ${active?.timeframe || targetTimeframe} trong màn Dạy Price Action.`);
        }
        return opened;
    }

    async function openPaTrainingSymbolFromAi(symbol, timeframe = state.paTrainingChartTimeframe || 'H4', options = {}) {
        const normalizedSymbol = normalizeSymbolKey(symbol);
        const requestedTimeframe = String(timeframe || state.paTrainingChartTimeframe || 'H4').toUpperCase();
        if (!normalizedSymbol) {
            showToast('AI chưa xác định được cặp tiền cần mở.');
            return false;
        }
        const file = findBestMarketFileForSymbol(normalizedSymbol, requestedTimeframe);
        if (!file) {
            showToast(normalizedSymbol + ' chưa có dữ liệu chart. Vào Dữ liệu & MT5 để lấy thêm.');
            return false;
        }
        const resolvedSymbol = file.symbol || normalizedSymbol;
        const resolvedTimeframe = String(file.timeframe || requestedTimeframe).toUpperCase();
        state.paTrainingSymbol = resolvedSymbol;
        state.paTrainingTimeframe = 'H4';
        state.paTrainingChartTimeframe = resolvedTimeframe;
        state.paTrainingZones = [];
        state.paTrainingTradeCases = [];
        state.paTrainingSelectedCaseId = '';
        state.paTrainingSkippedCaseIds = [];
        state.paTrainingQueueLoaded = false;
        state.paTrainingSelectedZoneId = '';
        state.paTrainingCaseError = '';
        state.paTrainingError = '';
        state.chartView.selectedTradeId = null;
        stopChartReplay(true);
        const symbolSelect = $('#paTrainingSymbolSelect');
        if (symbolSelect) {
            const exists = Array.from(symbolSelect.options || []).some((option) => normalizeSymbolKey(option.value) === normalizeSymbolKey(resolvedSymbol));
            if (!exists) symbolSelect.add(new Option(resolvedSymbol, resolvedSymbol));
            symbolSelect.value = resolvedSymbol;
        }
        const timeframeSelect = $('#paTrainingTimeframeSelect');
        if (timeframeSelect) timeframeSelect.value = 'H4';
        const opened = await loadPaTrainingChartForSymbol(resolvedSymbol, resolvedTimeframe, { silent: options.silent === true });
        if (opened && !options.silent) showToast('Đã mở ' + resolvedSymbol + ' ' + resolvedTimeframe + ' theo lệnh AI.');
        state.paTrainingChatLoadedKey = '';
        renderPaTraining();
        return opened;
    }
    async function openPaTrainingFullChart(timeframe = state.paTrainingChartTimeframe || 'H4') {
        const zone = paTrainingSelectedZone();
        const symbol = zone?.symbol || state.paTrainingSymbol;
        if (!symbol) {
            showToast('Chọn cặp tiền trước khi mở chart đầy đủ.');
            return;
        }
        const file = findMarketFile(symbol, timeframe);
        if (!file) {
            showToast(`${symbol} ${timeframe} chưa có dữ liệu. Vào Dữ liệu & MT5 để lấy thêm.`);
            return;
        }
        await loadMarketData(file.file);
        activateTab('mt5');
        showToast(`Đã mở chart đầy đủ ${symbol} ${timeframe}.`);
    }

    async function openPaTrainingSymbolChart(timeframe = 'H4') {
        const symbol = $('#paTrainingSymbolSelect')?.value || state.paTrainingSymbol;
        if (!symbol) {
            showToast('Chọn cặp tiền trước khi mở chart.');
            return;
        }
        const opened = await loadPaTrainingChartForSymbol(symbol, timeframe);
        if (opened) {
            const active = state.activeMarketData;
            showToast(`Đã mở chart ${active?.symbol || symbol} ${active?.timeframe || timeframe} trong màn Dạy Price Action.`);
        }
        return opened;
    }

    async function changePaWorkspaceTimeframe(timeframe) {
        const targetTimeframe = String(timeframe || 'H4').toUpperCase();
        const symbol = state.activeMarketData?.symbol || $('#paTrainingSymbolSelect')?.value || state.paTrainingSymbol;
        if (!symbol) {
            showToast('Chọn cặp tiền trước khi đổi khung.');
            return;
        }
        const previousData = state.activeMarketData;
        const previousBars = previousData?.bars || [];
        const replay = state.chartView.replay;
        const replayTime = replay.enabled
            ? (replay.time !== null && Number.isFinite(Number(replay.time)) ? Number(replay.time) : barTimeValue(previousBars[replay.index]) + timeframeSeconds(previousData?.timeframe) - 1)
            : null;
        const previousVisibleLast = previousBars.length
            ? clamp(previousBars.length - Math.max(0, Math.round(state.chartView.rightOffset || 0)) - 1, 0, previousBars.length - 1)
            : -1;
        const viewportRightTime = previousVisibleLast >= 0 ? barTimeValue(previousBars[previousVisibleLast]) : NaN;
        const view = {
            barsOnScreen: state.chartView.barsOnScreen,
            priceScale: state.chartView.priceScale,
            priceOffset: state.chartView.priceOffset,
        };
        const loaded = await loadPaTrainingChartForSymbol(symbol, targetTimeframe, { silent: true });
        if (loaded) {
            const bars = state.activeMarketData?.bars || [];
            if (replayTime !== null && Number.isFinite(replayTime) && bars.length) {
                replay.time = replayTime;
                replay.enabled = true;
                replay.index = chartIndexAtOrBeforeTime(bars, replayTime, replay.index);
                const previousSeconds = timeframeSeconds(previousData?.timeframe);
                const targetSeconds = timeframeSeconds(state.activeMarketData?.timeframe);
                const targetSpan = Math.round((view.barsOnScreen || 180) * previousSeconds / targetSeconds);
                state.chartView.barsOnScreen = clamp(targetSpan, Math.min(12, bars.length), bars.length);
                state.chartView.priceScale = view.priceScale;
                state.chartView.priceOffset = view.priceOffset;
                const visibleRight = Number.isFinite(viewportRightTime)
                    ? chartIndexAtOrBeforeTime(bars, viewportRightTime, bars.length - 1)
                    : bars.length - 1;
                state.chartView.rightOffset = clamp(
                    bars.length - 1 - visibleRight,
                    ...Object.values(chartRightOffsetLimits(bars.length, state.chartView.barsOnScreen))
                );
                renderReplayControls(state.activeMarketData);
                drawMarketChart(state.activeMarketData);
            }
            const teachingCase = paTrainingSelectedTeachingCase();
            if (replayTime === null && teachingCase && paTrainingCaseFitsChartData(teachingCase, state.activeMarketData)) {
                // Re-map the selected case's timestamp to the target timeframe's bar
                // index. Keeping the previous timeframe's replay index can place the
                // viewport and nearby zones at an unrelated price level.
                focusPaTrainingTeachingCaseWindow(teachingCase);
                persistPaWorkspace({
                    selectedCaseId: teachingCase.id,
                    replayIndex: state.chartView.replay.index,
                    replayEnabled: state.chartView.replay.enabled,
                });
            }
            persistPaWorkspace({ chartTimeframe: state.activeMarketData?.timeframe || targetTimeframe });
        }
        syncPaWorkspaceUi();
    }

    function renderTrainer() {
        const c = activeCase();
        if (!c) {
            $('#caseTitle').textContent = 'Chưa có tình huống';
            $('#casePosition').textContent = '0 / 0';
            $('#decisionSummary').innerHTML = '';
            $('#caseFields').innerHTML = '';
            return;
        }
        $('#caseTitle').textContent = `${c.symbol} ${displayDirection(c.direction)} | ${displaySetup(c.setup_type)}`;
        $('#casePosition').textContent = `${state.activeCaseIndex + 1} / ${state.candidateCases.length}`;
        $('#decisionSummary').innerHTML = decisionHtml(state.activeScore);
        $('#caseFields').innerHTML = caseFieldsHtml(c);
        $('#reviewNote').value = '';
        drawCaseChart(c);
    }

    function decisionHtml(score) {
        if (!score) {
            return '<div class="score-pill">Chưa chấm điểm</div>';
        }
        const klass = score.decision === 'enter' ? 'enter' : 'skip';
        const reasons = [...(score.reasons || []), ...(score.blockers || [])]
            .map((text) => `<span>${escapeHtml(translateDisplayText(text))}</span>`)
            .join('');
        return `
            <div class="score-pill ${klass}">${escapeHtml(displayAiDecision(score.decision))} · Độ tin cậy ${escapeHtml(score.confidence ?? 0)}/100</div>
            <div class="review-list">${reasons || '<span>Chưa có lý do trả về.</span>'}</div>
        `;
    }

    function caseFieldsHtml(c) {
        const summaryItems = [
            ['Cặp tiền', c.symbol || ''],
            ['Thời gian', c.time || ''],
            ['Mẫu lệnh', displaySetup(c.setup_type)],
            ['Hướng', displayDirection(c.direction)],
            ['Xu hướng H4', displayTrend(c.h4_trend)],
            ['Vùng H4', displayZone(c.h4_zone_type)],
            ['Điểm vùng', c.h4_zone_strength ?? ''],
            ['Cấu trúc M15', displayStructure(c.m15_structure)],
            ['Điểm vào', formatNumberValue(c.entry)],
            ['SL', formatNumberValue(c.sl)],
            ['TP', formatNumberValue(c.selected_tp)],
            ['RR', formatNumberValue(c.rr)],
        ];

        const fields = [
            ['symbol', 'Cặp tiền', 'text'],
            ['time', 'Thời gian', 'text'],
            ['setup_type', 'Mẫu lệnh', 'text'],
            ['direction', 'Hướng lệnh', 'text'],
            ['h4_trend', 'Xu hướng H4', 'text'],
            ['h4_zone_type', 'Vùng H4', 'text'],
            ['h4_zone_strength', 'Điểm vùng', 'number'],
            ['m15_structure', 'Cấu trúc M15', 'text'],
            ['entry', 'Điểm vào', 'number'],
            ['sl', 'SL', 'number'],
            ['selected_tp', 'TP', 'number'],
            ['rr', 'RR', 'number'],
        ];
        const editFields = fields
            .map(([key, label, type]) => {
                const value = c[key] ?? '';
                const options = caseFieldOptions(key, value);
                if (options.length) {
                    return `
                        <label class="case-edit-field">
                            <span>${escapeHtml(label)}</span>
                            <select data-case-key="${escapeAttr(key)}">
                                ${options.map((option) => `<option value="${escapeAttr(option)}" ${option === value ? 'selected' : ''}>${escapeHtml(displayCaseValue(key, option))}</option>`).join('')}
                            </select>
                        </label>
                    `;
                }
                return `
                    <label class="case-edit-field">
                        <span>${escapeHtml(label)}</span>
                        <input data-case-key="${escapeAttr(key)}" type="${type}" value="${escapeAttr(value)}">
                    </label>
                `;
            })
            .join('');

        return `
            <div class="case-summary-grid">
                ${summaryItems
                    .map(([label, value]) => {
                        return `
                            <div class="case-summary-item">
                                <span>${escapeHtml(label)}</span>
                                <strong>${escapeHtml(value)}</strong>
                            </div>
                        `;
                    })
                    .join('')}
            </div>
            <details class="case-editor">
                <summary>Sửa thông số tình huống</summary>
                <div class="case-edit-grid">${editFields}</div>
            </details>
        `;
    }

    function readEditedCase() {
        const original = activeCase();
        const edited = structuredClone(original);
        $$('[data-case-key]').forEach((input) => {
            const key = input.dataset.caseKey;
            edited[key] = input.type === 'number' ? num(input.value) : input.value.trim();
        });
        return edited;
    }

    function renderMemory() {
        const stats = state.stats || {};
        $('#memorySummary').innerHTML = [
            memoryRow('Định dạng', 'ai_trade_memory_v1'),
            memoryRow('Hồ sơ tài khoản', number(state.accounts.length)),
            memoryRow('Tình huống chờ duyệt', number(state.candidateCases.length)),
            memoryRow('Tình huống đã rà soát', number(stats.total_reviewed || 0)),
            memoryRow('Tình huống đã duyệt', number(stats.approved || 0)),
            memoryRow('Tình huống đã bỏ', number(stats.rejected || 0)),
            memoryRow('Tình huống đã sửa', number(stats.edited || 0)),
        ].join('');
    }

    function memoryRow(label, value) {
        return `<div class="memory-row"><strong>${escapeHtml(label)}</strong><span>${escapeHtml(value)}</span></div>`;
    }

    function memoryRowRaw(label, valueHtml) {
        return `<div class="memory-row"><strong>${escapeHtml(label)}</strong><span>${valueHtml}</span></div>`;
    }

    function strategyVersionReferenceRow(strategyVersionId) {
        if (!strategyVersionId) {
            return memoryRow('Phiên bản chiến lược', 'Không có');
        }

        return memoryRowRaw(
            'Phiên bản chiến lược',
            `<button class="inline-link strategy-v2-reference" type="button" data-strategy-v2-ref="${escapeAttr(strategyVersionId)}">${escapeHtml(strategyVersionId)}</button>`
        );
    }

    function renderBacktestLab() {
        const versionSelect = $('#backtestVersionSelect');
        if (!versionSelect) return;
        versionSelect.innerHTML = strategyVersionOptions(state.selectedStrategyVersionId);
        versionSelect.value = state.selectedStrategyVersionId || '';
        const symbolSelect = $('#backtestSymbolSelect');
        symbolSelect.innerHTML = symbolOptions();
        const backtestPresetSelect = $('#backtestPresetSelect');
        if (backtestPresetSelect) {
            const currentPreset = backtestPresetSelect.value || 'balanced';
            backtestPresetSelect.innerHTML = calibrationPresetOptions(currentPreset);
            backtestPresetSelect.value = [...backtestPresetSelect.options].some((option) => option.value === currentPreset) ? currentPreset : 'balanced';
        }

        const result = state.lastServerBacktest;
        $('#serverBacktestRunId').textContent = result ? (result.backtestRunId || result.id || '') : '';
        $('#serverBacktestMetrics').innerHTML = result ? serverBacktestMetricHtml(result.summary || result) : '<div class="notice">Chưa chạy kiểm thử chiến lược.</div>';
        $('#serverBacktestDiagnostics').innerHTML = result ? serverDiagnosticsHtml(result) : '';
        $('#serverBacktestCandidates').innerHTML = result ? serverSetupCandidatesHtml(result) : '';
        $('#serverBacktestTrades').innerHTML = result ? serverTradeListHtml(result.trades || []) : '';
        decorateZoneReview(result);
        decorateCandidateReview(result);
        decorateServerTradeRows(result ? result.trades || [] : []);
        if (result?.summary?.breakdowns) {
            $('#serverBacktestMetrics').insertAdjacentHTML('beforeend', serverBreakdownHtml(result.summary.breakdowns));
        }
    }

    function renderBacktestV2Results() {
        const list = $('#backtestV2ResultList');
        if (!list) return;

        const versionFilter = $('#backtestV2VersionFilter');
        if (versionFilter) {
            const current = state.backtestV2FilterVersionId || versionFilter.value || '';
            versionFilter.innerHTML = backtestV2VersionOptions(current);
            versionFilter.value = current;
        }

        const results = state.backtestV2Results || [];
        if (!state.selectedBacktestV2Id || !results.some((result) => result.id === state.selectedBacktestV2Id)) {
            state.selectedBacktestV2Id = results[0]?.id || '';
        }

        if (!results.length) {
            const emptyText = state.backtestV2Loading
                ? 'Đang tải kết quả kiểm thử V2...'
                : state.backtestV2Error || 'Chưa có kết quả kiểm thử V2.';
            list.innerHTML = `<div class="notice">${escapeHtml(emptyText)}</div>`;
        } else {
            list.innerHTML = results.map(backtestV2ListItemHtml).join('');
        }

        $$('.backtest-v2-item').forEach((button) => {
            button.addEventListener('click', () => {
                loadBacktestV2Detail(button.dataset.backtestV2Id).catch((error) => showToast(error.message));
            });
        });

        const selected = selectedBacktestV2Result();
        const detail = $('#backtestV2Detail');
        const metrics = $('#backtestV2Metrics');
        const runId = $('#backtestV2RunId');
        const evaluation = $('#backtestV2Evaluation');
        const evaluationStatus = $('#backtestV2EvaluationStatus');
        const reasons = $('#backtestV2Reasons');
        const recommendations = $('#backtestV2Recommendations');
        if (!detail || !metrics || !runId || !evaluation || !evaluationStatus || !reasons || !recommendations) return;

        runId.textContent = selected?.id || '';

        if (!selected) {
            detail.innerHTML = '<div class="notice">Chưa chọn kết quả kiểm thử V2.</div>';
            metrics.innerHTML = '';
            evaluation.innerHTML = '<div class="notice">Chưa có đánh giá KPI.</div>';
            evaluationStatus.textContent = '';
            reasons.innerHTML = '<div class="notice">Chưa có lý do đánh giá.</div>';
            recommendations.innerHTML = '<div class="notice">Chưa có khuyến nghị.</div>';
            return;
        }

        detail.innerHTML = [
            memoryRow('Cặp tiền', selected.symbol || 'Không có'),
            memoryRow('Khung thời gian', `${selected.higher_timeframe || 'H4'} / ${selected.timeframe || 'M15'}`),
            strategyVersionReferenceRow(selected.strategy_version_id || ''),
            memoryRow('Khoảng ngày', `${selected.start_date || 'Không rõ'} → ${selected.end_date || 'Không rõ'}`),
            memoryRow('Người tạo', selected.created_by || 'system'),
            memoryRow('Thời gian lưu', selected.created_at || ''),
        ].join('');
        detail.querySelectorAll('[data-strategy-v2-ref]').forEach((button) => {
            button.addEventListener('click', () => openStrategyV2Reference(button.dataset.strategyV2Ref).catch((error) => showToast(error.message)));
        });
        metrics.innerHTML = backtestV2MetricsHtml(selected);

        const latestEvaluation = state.selectedBacktestV2Evaluation;
        evaluationStatus.textContent = latestEvaluation ? translateStatus(latestEvaluation.status || '') : '';
        evaluation.innerHTML = latestEvaluation ? backtestV2EvaluationHtml(latestEvaluation) : '<div class="notice">Chưa có đánh giá KPI.</div>';
        reasons.innerHTML = latestEvaluation ? backtestV2ReasonsHtml(latestEvaluation.reasons_json || []) : '<div class="notice">Chưa có lý do đánh giá.</div>';
        recommendations.innerHTML = latestEvaluation ? backtestV2RecommendationsHtml(latestEvaluation.recommendations_json || []) : '<div class="notice">Chưa có khuyến nghị.</div>';
    }

    async function loadBacktestV2Results(preferredBacktestId = '') {
        state.backtestV2Loading = true;
        state.backtestV2Error = '';
        renderBacktestV2Results();

        try {
            const payload = { limit: 100 };
            if (state.backtestV2FilterVersionId) {
                payload.strategy_version_id = state.backtestV2FilterVersionId;
            }

            const data = await api('backtest_v2_list', payload);
            state.backtestV2Results = data.backtest_results || [];
            state.backtestV2Loaded = true;
            const preferredId = preferredBacktestId || state.selectedBacktestV2Id;
            state.selectedBacktestV2Id = preferredId && state.backtestV2Results.some((result) => result.id === preferredId)
                ? preferredId
                : state.backtestV2Results[0]?.id || '';

            if (state.selectedBacktestV2Id) {
                await loadBacktestV2Detail(state.selectedBacktestV2Id, false);
            } else {
                state.selectedBacktestV2Result = null;
                state.selectedBacktestV2Evaluations = [];
                state.selectedBacktestV2Evaluation = null;
                state.selectedBacktestV2BestEvaluation = null;
            }
        } catch (error) {
            state.backtestV2Error = error.message;
            throw error;
        } finally {
            state.backtestV2Loading = false;
            renderBacktestV2Results();
        }
    }

    async function loadBacktestV2Detail(backtestResultId, renderAfter = true) {
        if (!backtestResultId) {
            state.selectedBacktestV2Id = '';
            state.selectedBacktestV2Result = null;
            state.selectedBacktestV2Evaluations = [];
            state.selectedBacktestV2Evaluation = null;
            state.selectedBacktestV2BestEvaluation = null;
            if (renderAfter) renderBacktestV2Results();
            return;
        }

        state.selectedBacktestV2Id = backtestResultId;
        state.backtestV2Error = '';
        const [found, evaluations, latest, best] = await Promise.all([
            api('backtest_v2_find', { id: backtestResultId }),
            api('evaluation_list', { backtest_result_id: backtestResultId, limit: 50 }),
            api('evaluation_latest', { backtest_result_id: backtestResultId }),
            api('evaluation_best', { backtest_result_id: backtestResultId }),
        ]);
        let latestEvaluation = latest.evaluation || null;
        if (latestEvaluation?.id) {
            const foundEvaluation = await api('evaluation_find', { id: latestEvaluation.id });
            latestEvaluation = foundEvaluation.evaluation || latestEvaluation;
        }

        state.selectedBacktestV2Result = found.backtest_result || null;
        state.selectedBacktestV2Evaluations = evaluations.evaluations || [];
        state.selectedBacktestV2Evaluation = latestEvaluation || state.selectedBacktestV2Evaluations[0] || null;
        state.selectedBacktestV2BestEvaluation = best.evaluation || null;
        updateBacktestV2ResultInState(state.selectedBacktestV2Result);
        if (renderAfter) renderBacktestV2Results();
    }

    function selectedBacktestV2Result() {
        if (state.selectedBacktestV2Result?.id === state.selectedBacktestV2Id) {
            return state.selectedBacktestV2Result;
        }

        return (state.backtestV2Results || []).find((result) => result.id === state.selectedBacktestV2Id) || null;
    }

    function updateBacktestV2ResultInState(result) {
        if (!result?.id) return;
        const index = state.backtestV2Results.findIndex((item) => item.id === result.id);
        if (index >= 0) {
            state.backtestV2Results[index] = result;
        } else {
            state.backtestV2Results.unshift(result);
        }
    }

    function backtestV2ListItemHtml(result) {
        const active = result.id === state.selectedBacktestV2Id ? ' is-active' : '';
        return `
            <button class="profile-item backtest-v2-item${active}" type="button" data-backtest-v2-id="${escapeAttr(result.id)}">
                <strong>${escapeHtml(result.symbol || 'UNKNOWN')} | ${escapeHtml(result.higher_timeframe || 'H4')} / ${escapeHtml(result.timeframe || 'M15')}</strong>
                <span>${escapeHtml(result.strategy_version_id || 'Không có phiên bản')} | Win ${formatFixed(result.win_rate || 0, 2)}% | RR ${formatFixed(result.average_rr || 0, 2)} | PF ${formatFixed(result.profit_factor || 0, 2)}</span>
                <span>DD ${formatFixed(result.max_drawdown || 0, 2)}% | ${number(result.total_trades || 0)} lệnh | ${escapeHtml(result.created_at || '')}</span>
            </button>
        `;
    }

    function backtestV2MetricsHtml(result) {
        return [
            backtestMetricHtml('Win rate', `${formatFixed(result.win_rate || 0, 2)}%`),
            backtestMetricHtml('Average RR', formatFixed(result.average_rr || 0, 2)),
            backtestMetricHtml('Profit factor', formatFixed(result.profit_factor || 0, 2)),
            backtestMetricHtml('Max drawdown', `${formatFixed(result.max_drawdown || 0, 2)}%`),
            backtestMetricHtml('Tổng lệnh', number(result.total_trades || 0)),
            backtestMetricHtml('Lệnh thắng / thua', `${number(result.winning_trades || 0)} / ${number(result.losing_trades || 0)}`),
            backtestMetricHtml('Số dư đầu', formatFixed(result.initial_balance || 0, 2)),
            backtestMetricHtml('Số dư cuối', formatFixed(result.final_balance || 0, 2)),
        ].join('');
    }

    function backtestV2EvaluationHtml(evaluation) {
        return [
            memoryRow('Trạng thái', translateStatus(evaluation.status || '')),
            memoryRow('Điểm', `${formatFixed(evaluation.score || 0, 2)}/100`),
            memoryRow('Kết luận', evaluation.passed ? 'Đạt KPI' : 'Chưa đạt KPI'),
            memoryRow('Backtest', evaluation.backtest_result_id || 'Không có'),
            memoryRow('Thí nghiệm', evaluation.experiment_id || 'Không có'),
            memoryRow('Thời gian', evaluation.created_at || ''),
        ].join('');
    }

    function backtestV2ReasonsHtml(reasons) {
        if (!Array.isArray(reasons) || !reasons.length) {
            return '<div class="notice">Chưa có lý do đánh giá.</div>';
        }

        return reasons
            .map((reason) => {
                const passed = reason.passed ? 'Đạt' : 'Chưa đạt';
                return `
                    <div class="review-item">
                        <strong>${escapeHtml(evaluationMetricLabel(reason.metric || reason.label || ''))} | ${escapeHtml(passed)}</strong>
                        <span>${escapeHtml(formatFixed(reason.actual || 0, 2))} ${escapeHtml(reason.operator || '')} ${escapeHtml(formatFixed(reason.target || 0, 2))}</span>
                    </div>
                `;
            })
            .join('');
    }

    function backtestV2RecommendationsHtml(recommendations) {
        if (!Array.isArray(recommendations) || !recommendations.length) {
            return '<div class="notice">Chưa có khuyến nghị.</div>';
        }

        return recommendations
            .map((item) => `<div class="review-item"><strong>${escapeHtml(translateEvaluationRecommendation(item))}</strong></div>`)
            .join('');
    }

    function backtestV2VersionOptions(selectedId = '') {
        const all = `<option value="" ${selectedId === '' ? 'selected' : ''}>Tất cả phiên bản</option>`;
        const seen = new Set();
        const options = [];
        (state.strategyVersions || []).forEach((version) => {
            if (!version?.id || seen.has(version.id)) return;
            seen.add(version.id);
            options.push(`<option value="${escapeAttr(version.id)}" ${version.id === selectedId ? 'selected' : ''}>${escapeHtml(displayStrategyVersionName(version.name))} | ${escapeHtml(translateStatus(version.status))}</option>`);
        });
        (state.strategyV2Versions || []).forEach((version) => {
            if (!version?.id || seen.has(version.id)) return;
            seen.add(version.id);
            options.push(`<option value="${escapeAttr(version.id)}" ${version.id === selectedId ? 'selected' : ''}>${escapeHtml(strategyV2Label(version))} | V2</option>`);
        });
        if (selectedId && !seen.has(selectedId)) {
            options.push(`<option value="${escapeAttr(selectedId)}" selected>${escapeHtml(selectedId)} | V2</option>`);
        }

        return all + options.join('');
    }

    function evaluationMetricLabel(metric) {
        const labels = {
            win_rate: 'Win rate',
            average_rr: 'Average RR',
            profit_factor: 'Profit factor',
            max_drawdown: 'Max drawdown',
            total_trades: 'Total trades',
        };

        return labels[metric] || formatDisplayLabel(metric);
    }

    function translateEvaluationRecommendation(text) {
        const recommendations = {
            'Improve setup quality or add filters to raise win rate.': 'Cải thiện chất lượng setup hoặc thêm bộ lọc để tăng win rate.',
            'Improve reward-to-risk quality by tightening entries, improving targets, or rejecting low-RR setups.': 'Cải thiện RR bằng điểm vào chặt hơn, mục tiêu tốt hơn hoặc loại setup RR thấp.',
            'Improve profit factor by reducing loss frequency, loss size, or low-quality trades.': 'Tăng profit factor bằng cách giảm tần suất thua, độ lớn lệnh thua hoặc lệnh chất lượng thấp.',
            'Reduce drawdown with stricter risk, fewer correlated trades, or stronger invalidation rules.': 'Giảm drawdown bằng rủi ro chặt hơn, ít lệnh tương quan hơn hoặc luật vô hiệu mạnh hơn.',
            'Run a larger sample before trusting this strategy evaluation.': 'Cần mẫu giao dịch lớn hơn trước khi tin kết quả đánh giá.',
            'Review this KPI before promotion.': 'Rà soát KPI này trước khi phê duyệt.',
        };

        return recommendations[text] || translateDisplayText(text);
    }

    function renderKPIProfiles() {
        const list = $('#kpiProfileList');
        if (!list) return;

        const profiles = state.kpiProfiles || [];
        if (!state.selectedKPIProfileId || !profiles.some((profile) => profile.id === state.selectedKPIProfileId)) {
            state.selectedKPIProfileId = profiles[0]?.id || '';
        }

        const defaultBox = $('#kpiProfileDefault');
        if (defaultBox) {
            defaultBox.innerHTML = state.defaultKPIProfile
                ? kpiDefaultProfileHtml(state.defaultKPIProfile)
                : '<div class="notice">Chưa có hồ sơ KPI mặc định.</div>';
        }

        if (!profiles.length) {
            const emptyText = state.kpiProfilesLoading
                ? 'Đang tải cấu hình KPI...'
                : state.kpiProfilesError || 'Chưa có hồ sơ KPI.';
            list.innerHTML = `<div class="notice">${escapeHtml(emptyText)}</div>`;
        } else {
            list.innerHTML = profiles.map(kpiProfileListItemHtml).join('');
        }

        $$('.kpi-profile-item').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedKPIProfileId = button.dataset.kpiProfileId;
                renderKPIProfiles();
            });
        });

        fillKPIProfileForm(selectedKPIProfile());
    }

    async function loadKPIProfiles(preferredProfileId = '') {
        state.kpiProfilesLoading = true;
        state.kpiProfilesError = '';
        renderKPIProfiles();

        try {
            const data = await api('kpi_profile_list', { limit: 100 });
            state.kpiProfiles = data.profiles || [];
            state.defaultKPIProfile = data.default_profile || null;
            state.kpiProfilesLoaded = true;
            const preferredId = preferredProfileId || state.selectedKPIProfileId;
            state.selectedKPIProfileId = preferredId && state.kpiProfiles.some((profile) => profile.id === preferredId)
                ? preferredId
                : state.kpiProfiles[0]?.id || '';
        } catch (error) {
            state.kpiProfilesError = error.message;
            throw error;
        } finally {
            state.kpiProfilesLoading = false;
            renderKPIProfiles();
        }
    }

    async function saveKPIProfile(event) {
        event.preventDefault();
        const payload = readKPIProfileForm();
        if (!payload.name) {
            showToast('Nhập tên hồ sơ KPI trước khi lưu.');
            return;
        }

        const action = state.selectedKPIProfileId ? 'kpi_profile_update' : 'kpi_profile_create';
        if (state.selectedKPIProfileId) {
            payload.id = state.selectedKPIProfileId;
        }

        const data = await api(action, payload);
        state.kpiProfiles = data.profiles || state.kpiProfiles;
        state.defaultKPIProfile = data.default_profile || state.defaultKPIProfile;
        state.selectedKPIProfileId = data.profile?.id || state.selectedKPIProfileId;
        state.kpiProfilesLoaded = true;
        renderKPIProfiles();
        showToast('Đã lưu hồ sơ KPI');
    }

    async function setDefaultKPIProfile() {
        if (!state.selectedKPIProfileId) {
            showToast('Chọn hồ sơ KPI trước.');
            return;
        }

        const data = await api('kpi_profile_set_default', { id: state.selectedKPIProfileId });
        state.kpiProfiles = data.profiles || state.kpiProfiles;
        state.defaultKPIProfile = data.default_profile || data.profile || null;
        renderKPIProfiles();
        showToast('Đã đặt hồ sơ KPI mặc định');
    }

    async function deleteKPIProfile() {
        if (!state.selectedKPIProfileId) {
            showToast('Chọn hồ sơ KPI trước.');
            return;
        }

        const deletingId = state.selectedKPIProfileId;
        const data = await api('kpi_profile_delete', { id: deletingId });
        state.kpiProfiles = data.profiles || [];
        state.defaultKPIProfile = data.default_profile || null;
        state.selectedKPIProfileId = state.kpiProfiles[0]?.id || '';
        renderKPIProfiles();
        showToast('Đã xóa hồ sơ KPI');
    }

    function clearKPIProfileForm() {
        state.selectedKPIProfileId = '';
        fillKPIProfileForm(null);
    }

    function selectedKPIProfile() {
        return (state.kpiProfiles || []).find((profile) => profile.id === state.selectedKPIProfileId) || null;
    }

    function kpiProfileListItemHtml(profile) {
        const active = profile.id === state.selectedKPIProfileId ? ' is-active' : '';
        const defaultBadge = profile.is_default ? '<span class="status-badge production">Mặc định</span>' : '<span class="status-badge">Tùy chỉnh</span>';
        return `
            <button class="profile-item kpi-profile-item${active}" type="button" data-kpi-profile-id="${escapeAttr(profile.id)}">
                <div class="kpi-profile-head">
                    <strong>${escapeHtml(profile.name || profile.id)}</strong>
                    ${defaultBadge}
                </div>
                ${kpiMetricGridHtml(profile, true)}
                <span class="kpi-profile-meta">Cập nhật ${escapeHtml(formatDateTime(profile.updated_at || ''))}</span>
            </button>
        `;
    }

    function kpiDefaultProfileHtml(profile) {
        return `
            <div class="kpi-default-card">
                <div class="kpi-profile-head">
                    <div>
                        <strong>${escapeHtml(profile.name || profile.id)}</strong>
                        <span>Hồ sơ KPI mặc định đang dùng để đánh giá backtest.</span>
                    </div>
                    <span class="status-badge production">Mặc định</span>
                </div>
                ${kpiMetricGridHtml(profile)}
            </div>
        `;
    }

    function kpiMetricGridHtml(profile, compact = false) {
        return `
            <div class="kpi-metric-grid${compact ? ' compact' : ''}">
                ${kpiMetricHtml('Tỷ lệ thắng', `${formatFixed(profile.win_rate_min || 0, 2)}%`)}
                ${kpiMetricHtml('RR trung bình', formatFixed(profile.average_rr_min || 0, 2))}
                ${kpiMetricHtml('Tổng lời / tổng lỗ', `${formatFixed(profile.profit_factor_min || 0, 2)}x`)}
                ${kpiMetricHtml('Sụt giảm vốn', `${formatFixed(profile.max_drawdown_max || 0, 2)}%`)}
                ${kpiMetricHtml('Số lệnh', `${number(profile.total_trades_min || 0)} lệnh`)}
            </div>
        `;
    }

    function kpiMetricHtml(label, value) {
        return `
            <div class="kpi-metric">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
            </div>
        `;
    }

    function fillKPIProfileForm(profile) {
        const name = $('#kpiProfileNameInput');
        if (!name) return;

        name.value = profile?.name || '';
        $('#kpiProfileDescriptionInput').value = profile?.description || '';
        $('#kpiWinRateMinInput').value = profile?.win_rate_min ?? 70;
        $('#kpiAverageRRMinInput').value = profile?.average_rr_min ?? 2;
        $('#kpiProfitFactorMinInput').value = profile?.profit_factor_min ?? 2;
        $('#kpiMaxDrawdownMaxInput').value = profile?.max_drawdown_max ?? 10;
        $('#kpiTotalTradesMinInput').value = profile?.total_trades_min ?? 300;
        $('#kpiProfileNotesInput').value = profile?.notes || '';
        $('#setDefaultKPIProfileBtn').disabled = !profile || !!profile.is_default;
        $('#deleteKPIProfileBtn').disabled = !profile;
    }

    function readKPIProfileForm() {
        return {
            name: $('#kpiProfileNameInput').value.trim(),
            description: $('#kpiProfileDescriptionInput').value.trim(),
            win_rate_min: num($('#kpiWinRateMinInput').value),
            average_rr_min: num($('#kpiAverageRRMinInput').value),
            profit_factor_min: num($('#kpiProfitFactorMinInput').value),
            max_drawdown_max: num($('#kpiMaxDrawdownMaxInput').value),
            total_trades_min: Math.max(0, Math.round(num($('#kpiTotalTradesMinInput').value))),
            notes: $('#kpiProfileNotesInput').value.trim(),
        };
    }

    function renderExperiments() {
        const list = $('#experimentList');
        if (!list) return;

        const experiments = state.experiments || [];
        if (!state.selectedExperimentId || !experiments.some((experiment) => experiment.id === state.selectedExperimentId)) {
            state.selectedExperimentId = experiments[0]?.id || '';
        }

        if (!experiments.length) {
            const emptyText = state.experimentLoading
                ? 'Đang tải danh sách thí nghiệm...'
                : state.experimentError || 'Chưa có thí nghiệm.';
            list.innerHTML = `<div class="notice">${escapeHtml(emptyText)}</div>`;
        } else {
            list.innerHTML = experiments
                .map((experiment) => {
                    const active = experiment.id === state.selectedExperimentId ? ' is-active' : '';
                    const status = translateStatus(experiment.status || 'Draft');
                    return `
                        <button class="profile-item experiment-item${active}" type="button" data-experiment-id="${escapeAttr(experiment.id)}">
                            <strong>${escapeHtml(experiment.name || experiment.id)}</strong>
                            <span>${escapeHtml(status)} | ${escapeHtml(experiment.created_at || '')} | ${escapeHtml(experiment.created_by || '')}</span>
                        </button>
                    `;
                })
                .join('');
        }

        $$('.experiment-item').forEach((button) => {
            button.addEventListener('click', () => {
                loadExperimentDetail(button.dataset.experimentId).catch((error) => showToast(error.message));
            });
        });

        const selected = selectedExperiment();
        const detail = $('#experimentDetail');
        const detailStatus = $('#experimentDetailStatus');
        const actions = $('#experimentActions');
        const bestItem = $('#experimentBestItem');
        const items = $('#experimentItems');
        const itemCount = $('#experimentItemCount');
        if (!detail || !detailStatus || !actions || !bestItem || !items || !itemCount) return;

        actions.hidden = !selected;
        detailStatus.textContent = selected ? translateStatus(selected.status || '') : '';

        if (!selected) {
            detail.innerHTML = '<div class="notice">Chưa chọn thí nghiệm.</div>';
            bestItem.innerHTML = '<div class="notice">Chưa có kết quả tốt nhất.</div>';
            items.innerHTML = '<div class="notice">Chưa có kết quả trong thí nghiệm.</div>';
            itemCount.textContent = '0';
            return;
        }

        detail.innerHTML = [
            memoryRow('Tên', selected.name || selected.id),
            memoryRow('Trạng thái', translateStatus(selected.status || '')),
            memoryRow('Mục tiêu', experimentGoalText(selected)),
            memoryRow('Bắt đầu', selected.started_at || 'Chưa bắt đầu'),
            memoryRow('Hoàn thành', selected.completed_at || 'Chưa hoàn thành'),
            memoryRow('Ghi chú', selected.notes || 'Không có'),
        ].join('');

        bestItem.innerHTML = experimentBestItemHtml(state.experimentBestItem);
        const experimentItems = state.experimentItems || [];
        itemCount.textContent = String(experimentItems.length);
        items.innerHTML = experimentItems.length
            ? experimentItems.map(experimentItemHtml).join('')
            : '<div class="notice">Chưa có kết quả trong thí nghiệm.</div>';
    }

    async function loadExperiments(preferredExperimentId = '') {
        state.experimentLoading = true;
        state.experimentError = '';
        renderExperiments();

        try {
            const data = await api('experiment_list', { limit: 100 });
            state.experiments = data.experiments || [];
            state.experimentsLoaded = true;
            const preferredId = preferredExperimentId || state.selectedExperimentId;
            state.selectedExperimentId = preferredId && state.experiments.some((experiment) => experiment.id === preferredId)
                ? preferredId
                : state.experiments[0]?.id || '';

            if (state.selectedExperimentId) {
                await loadExperimentDetail(state.selectedExperimentId, false);
            } else {
                state.experimentDetail = null;
                state.experimentItems = [];
                state.experimentBestItem = null;
            }
        } catch (error) {
            state.experimentError = error.message;
            throw error;
        } finally {
            state.experimentLoading = false;
            renderExperiments();
        }
    }

    async function loadExperimentDetail(experimentId, renderAfter = true) {
        if (!experimentId) {
            state.selectedExperimentId = '';
            state.experimentDetail = null;
            state.experimentItems = [];
            state.experimentBestItem = null;
            if (renderAfter) renderExperiments();
            return;
        }

        state.selectedExperimentId = experimentId;
        state.experimentError = '';
        const [found, items, best] = await Promise.all([
            api('experiment_find', { id: experimentId }),
            api('experiment_list_items', { id: experimentId, limit: 100 }),
            api('experiment_best_item', { id: experimentId }),
        ]);

        state.experimentDetail = found.experiment || null;
        state.experimentItems = items.items || [];
        state.experimentBestItem = best.item || null;
        updateExperimentInState(state.experimentDetail);
        if (renderAfter) renderExperiments();
    }

    async function createExperiment(event) {
        event.preventDefault();
        const name = $('#experimentNameInput').value.trim();
        const goal = $('#experimentGoalInput').value.trim();
        const notes = $('#experimentNotesInput').value.trim();
        if (!name) {
            showToast('Nhập tên thí nghiệm trước khi tạo.');
            return;
        }

        const data = await api('experiment_create', {
            name,
            description: goal,
            goal: goal ? { objective: goal } : {},
            notes,
            created_by: 'user',
        });
        state.experiments = data.experiments || state.experiments;
        state.experimentsLoaded = true;
        state.selectedExperimentId = data.experiment?.id || state.selectedExperimentId;
        $('#experimentNameInput').value = '';
        $('#experimentGoalInput').value = '';
        $('#experimentNotesInput').value = '';
        if (state.selectedExperimentId) {
            await loadExperimentDetail(state.selectedExperimentId, false);
        }
        renderExperiments();
        showToast('Đã tạo thí nghiệm');
    }

    async function changeExperimentStatus(action) {
        const experimentId = state.selectedExperimentId;
        if (!experimentId) {
            showToast('Chọn thí nghiệm trước.');
            return;
        }
        const actionMap = {
            start: 'experiment_start',
            pause: 'experiment_pause',
            complete: 'experiment_complete',
            archive: 'experiment_archive',
        };
        const apiAction = actionMap[action];
        if (!apiAction) {
            showToast('Thao tác thí nghiệm không hợp lệ.');
            return;
        }

        const payload = { id: experimentId };
        if (action === 'complete') {
            payload.summary = { completedBy: 'user', completedAt: new Date().toISOString() };
        }

        const data = await api(apiAction, payload);
        state.experimentDetail = data.experiment || state.experimentDetail;
        updateExperimentInState(state.experimentDetail);
        await loadExperimentDetail(experimentId, false);
        renderExperiments();
        showToast(`Đã cập nhật thí nghiệm: ${translateStatus(state.experimentDetail?.status || '')}`);
    }

    function selectedExperiment() {
        if (state.experimentDetail?.id === state.selectedExperimentId) {
            return state.experimentDetail;
        }

        return (state.experiments || []).find((experiment) => experiment.id === state.selectedExperimentId) || null;
    }

    function updateExperimentInState(experiment) {
        if (!experiment?.id) return;
        const index = state.experiments.findIndex((item) => item.id === experiment.id);
        if (index >= 0) {
            state.experiments[index] = experiment;
        } else {
            state.experiments.unshift(experiment);
        }
    }

    function experimentGoalText(experiment) {
        const goal = experiment?.goal_json || {};
        if (typeof goal === 'string') return goal || 'Chưa có mục tiêu';
        if (goal.objective) return goal.objective;
        const entries = Object.entries(goal).filter(([, value]) => value !== null && value !== '');
        if (!entries.length) return experiment?.description || 'Chưa có mục tiêu';
        return entries.map(([key, value]) => `${formatDisplayLabel(key)}: ${displayAdvisorValue(value)}`).join(' | ');
    }

    function experimentBestItemHtml(item) {
        if (!item) return '<div class="notice">Chưa có kết quả tốt nhất.</div>';

        return [
            memoryRow('Trạng thái', translateStatus(item.status || '')),
            memoryRow('Điểm', experimentScore(item.score)),
            memoryRow('Phiên bản', item.strategy_version_id || 'Không có'),
            memoryRow('Backtest', item.backtest_result_id || 'Không có'),
            memoryRow('Thời gian', item.created_at || ''),
        ].join('');
    }

    function experimentItemHtml(item) {
        const metrics = item.metrics_json || {};
        const reasons = Array.isArray(metrics.reasons)
            ? metrics.reasons.join(', ')
            : Array.isArray(metrics.reasons_json)
              ? metrics.reasons_json.join(', ')
              : '';
        const recommendationCount = Array.isArray(metrics.recommendations)
            ? metrics.recommendations.length
            : Array.isArray(metrics.recommendations_json)
              ? metrics.recommendations_json.length
              : 0;

        return `
            <div class="review-item experiment-result-item">
                <strong>${escapeHtml(translateStatus(item.status || ''))} | ${escapeHtml(experimentScore(item.score))}</strong>
                <span>${escapeHtml(formatDisplayLabel(item.item_type || 'BacktestResult', 'statuses'))} | Phiên bản: ${escapeHtml(item.strategy_version_id || 'Không có')} | Backtest: ${escapeHtml(item.backtest_result_id || 'Không có')}</span>
                <span>${escapeHtml(item.created_at || '')}${reasons ? ` | Lý do: ${escapeHtml(reasons)}` : ''}${recommendationCount ? ` | ${recommendationCount} khuyến nghị` : ''}</span>
            </div>
        `;
    }

    function experimentScore(value) {
        return value === null || value === undefined || value === '' ? 'Chưa chấm điểm' : `${formatFixed(value, 2)}/100`;
    }

    function renderAIJobs() {
        const list = $('#aiJobList');
        if (!list) return;

        renderAIJobFormOptions();
        const jobs = state.aiJobs || [];
        $('#aiJobCount').textContent = String(jobs.length);
        const researchButton = $('#runResearchMockOnceBtn');
        if (researchButton) {
            researchButton.disabled = state.researchMockRunning;
            researchButton.textContent = state.researchMockRunning ? 'Đang chạy...' : 'Chạy nghiên cứu thử';
        }
        const workerButton = $('#runAIWorkerOnceBtn');
        if (workerButton) {
            workerButton.disabled = state.aiWorkerRunning || (state.aiJobsLoaded && !jobs.some((job) => job.status === 'Pending'));
            workerButton.textContent = state.aiWorkerRunning ? 'Đang chạy...' : 'Chạy worker thử';
        }

        if (!state.selectedAIJobId || !jobs.some((job) => job.id === state.selectedAIJobId)) {
            state.selectedAIJobId = jobs[0]?.id || '';
        }

        if (!jobs.length) {
            const emptyText = state.aiJobsLoading
                ? 'Đang tải AI jobs...'
                : state.aiJobsError || 'Chưa có AI job.';
            list.innerHTML = `<div class="notice">${escapeHtml(translateDisplayText(emptyText))}</div>`;
        } else {
            list.innerHTML = jobs.map(aiJobListItemHtml).join('');
        }

        $$('.ai-job-item').forEach((button) => {
            button.addEventListener('click', () => {
                loadAIJobDetail(button.dataset.aiJobId).catch((error) => showToast(error.message));
            });
        });

        renderAIJobDetail(selectedAIJob());
    }

    function renderAIJobFormOptions() {
        const strategySelect = $('#aiJobStrategyVersionInput');
        if (strategySelect) {
            const current = strategySelect.value || '';
            strategySelect.innerHTML = aiJobStrategyVersionOptions(current);
            strategySelect.value = [...strategySelect.options].some((option) => option.value === current) ? current : '';
        }

        const experimentSelect = $('#aiJobExperimentInput');
        if (experimentSelect) {
            const current = experimentSelect.value || '';
            experimentSelect.innerHTML = '<option value="">Không gắn thí nghiệm</option>'
                + (state.experiments || [])
                    .map((experiment) => `<option value="${escapeAttr(experiment.id)}" ${experiment.id === current ? 'selected' : ''}>${escapeHtml(experiment.name || experiment.id)} | ${escapeHtml(translateStatus(experiment.status || ''))}</option>`)
                    .join('');
            experimentSelect.value = [...experimentSelect.options].some((option) => option.value === current) ? current : '';
        }
    }

    function aiJobStrategyVersionOptions(selectedId = '') {
        const seen = new Set();
        const options = ['<option value="">Không gắn phiên bản</option>'];
        (state.strategyVersions || []).forEach((version) => {
            if (!version?.id || seen.has(version.id)) return;
            seen.add(version.id);
            options.push(`<option value="${escapeAttr(version.id)}" ${version.id === selectedId ? 'selected' : ''}>${escapeHtml(displayStrategyVersionName(version.name))} | ${escapeHtml(translateStatus(version.status))}</option>`);
        });
        (state.strategyV2Versions || []).forEach((version) => {
            if (!version?.id || seen.has(version.id)) return;
            seen.add(version.id);
            options.push(`<option value="${escapeAttr(version.id)}" ${version.id === selectedId ? 'selected' : ''}>${escapeHtml(strategyV2Label(version))} | V2</option>`);
        });
        if (selectedId && !seen.has(selectedId)) {
            options.push(`<option value="${escapeAttr(selectedId)}" selected>${escapeHtml(selectedId)}</option>`);
        }

        return options.join('');
    }

    function aiJobListItemHtml(job) {
        const active = job.id === state.selectedAIJobId ? ' is-active' : '';
        return `
            <button class="profile-item ai-job-item${active}" type="button" data-ai-job-id="${escapeAttr(job.id)}">
                <strong>${escapeHtml(aiJobTypeLabel(job.job_type || job.jobType))} | ${escapeHtml(translateStatus(job.status || ''))}</strong>
                <span>${escapeHtml(job.strategy_version_id || 'Không gắn phiên bản')} | ${escapeHtml(job.experiment_id || 'Không gắn thí nghiệm')}</span>
                <span>${escapeHtml(job.created_at || '')} | ${escapeHtml(job.created_by || 'user')}</span>
            </button>
        `;
    }

    function renderAIJobDetail(job) {
        const detail = $('#aiJobDetail');
        const cancelButton = $('#cancelAIJobBtn');
        const draftButton = $('#createAIJobStrategyDraftBtn');
        if (!detail || !cancelButton || !draftButton) return;

        cancelButton.disabled = job?.status !== 'Pending';
        const canCreateDraft = canCreateStrategyDraftFromAIJob(job);
        draftButton.hidden = !canCreateDraft;
        draftButton.disabled = !canCreateDraft;
        if (!job) {
            detail.innerHTML = '<div class="notice">Chưa chọn AI job.</div>';
            return;
        }

        detail.innerHTML = `
            <div class="version-detail-head">
                <div>
                    <h3>${escapeHtml(aiJobTypeLabel(job.job_type || job.jobType))}</h3>
                    <span>${escapeHtml(job.id)} | ${escapeHtml(translateStatus(job.status || ''))}</span>
                </div>
                <span class="status-badge ${job.status === 'Cancelled' ? 'archived' : ''}">${escapeHtml(translateStatus(job.status || ''))}</span>
            </div>
            <div class="memory-summary">
                ${memoryRow('Phiên bản chiến lược', job.strategy_version_id || 'Không có')}
                ${memoryRow('Thí nghiệm', job.experiment_id || 'Không có')}
                ${memoryRow('Người tạo', job.created_by || 'user')}
                ${memoryRow('Tạo lúc', job.created_at || '')}
                ${memoryRow('Bắt đầu', job.started_at || 'Chưa bắt đầu')}
                ${memoryRow('Hoàn thành', job.completed_at || 'Chưa hoàn thành')}
                ${memoryRow('Ghi chú', job.notes || 'Không có')}
            </div>
            <div class="strategy-v2-json-grid">
                <details open class="json-details">
                    <summary>Input</summary>
                    <pre>${escapeHtml(JSON.stringify(job.input_json || {}, null, 2))}</pre>
                </details>
                <details open class="json-details">
                    <summary>Output</summary>
                    <pre>${escapeHtml(JSON.stringify(job.output_json || {}, null, 2))}</pre>
                </details>
                <details open class="json-details">
                    <summary>Lỗi</summary>
                    <pre>${escapeHtml(job.error_message || 'Không có')}</pre>
                </details>
            </div>
        `;
    }

    async function loadAIJobs(preferredJobId = '') {
        state.aiJobsLoading = true;
        state.aiJobsError = '';
        renderAIJobs();

        try {
            const data = await api('ai_job_list', { limit: 100 });
            state.aiJobs = data.ai_jobs || [];
            state.aiJobsLoaded = true;
            state.selectedAIJobId = preferredJobId && state.aiJobs.some((job) => job.id === preferredJobId)
                ? preferredJobId
                : state.selectedAIJobId || state.aiJobs[0]?.id || '';
            if (state.selectedAIJobId) {
                await loadAIJobDetail(state.selectedAIJobId, false);
            }
        } catch (error) {
            state.aiJobsError = error.message;
            throw error;
        } finally {
            state.aiJobsLoading = false;
            renderAIJobs();
        }
    }

    async function loadAIJobDetail(jobId, renderAfter = true) {
        if (!jobId) {
            state.selectedAIJobId = '';
            if (renderAfter) renderAIJobs();
            return;
        }

        const data = await api('ai_job_find', { id: jobId });
        state.selectedAIJobId = jobId;
        updateAIJobInState(data.ai_job);
        if (renderAfter) renderAIJobs();
    }

    async function createAIJob(event) {
        event.preventDefault();
        const payload = {
            job_type: $('#aiJobTypeInput').value,
            strategy_version_id: $('#aiJobStrategyVersionInput').value || null,
            experiment_id: $('#aiJobExperimentInput').value || null,
            created_by: $('#aiJobCreatedByInput').value.trim() || 'user',
            notes: $('#aiJobNotesInput').value.trim(),
            input_json: parseJsonTextarea('aiJobInputJson'),
        };
        const data = await api('ai_job_create', payload);
        state.aiJobs = data.ai_jobs || state.aiJobs;
        state.aiJobsLoaded = true;
        state.selectedAIJobId = data.ai_job?.id || state.selectedAIJobId;
        $('#aiJobInputJson').value = '{}';
        $('#aiJobNotesInput').value = '';
        showToast('Đã tạo AI job thủ công');
        renderAIJobs();
    }

    async function cancelAIJob() {
        const job = selectedAIJob();
        if (!job) throw new Error('Chưa chọn AI job.');
        const data = await api('ai_job_cancel', { id: job.id });
        state.aiJobs = data.ai_jobs || state.aiJobs;
        updateAIJobInState(data.ai_job);
        state.selectedAIJobId = data.ai_job?.id || state.selectedAIJobId;
        showToast('Đã hủy AI job đang chờ');
        renderAIJobs();
    }

    async function runAIWorkerOnce() {
        state.aiWorkerRunning = true;
        renderAIJobs();
        try {
            const data = await api('ai_worker_run_once', {});
            state.aiJobs = data.ai_jobs || state.aiJobs;
            if (data.ai_job) {
                updateAIJobInState(data.ai_job);
                state.selectedAIJobId = data.ai_job.id;
            }
            showToast(data.processed ? 'Đã chạy worker thử' : 'Không có AI job đang chờ');
        } finally {
            state.aiWorkerRunning = false;
            renderAIJobs();
        }
    }

    async function runResearchMockOnce() {
        state.researchMockRunning = true;
        renderAIJobs();
        try {
            const data = await api('research_mock_run_once', {});
            state.aiJobs = data.ai_jobs || state.aiJobs;
            if (data.ai_job) {
                updateAIJobInState(data.ai_job);
                state.selectedAIJobId = data.ai_job.id;
            }
            if (data.experiments) {
                state.experiments = data.experiments;
                state.experimentsLoaded = true;
            }
            if (data.experiment) {
                const sameExperiment = state.selectedExperimentId === data.experiment.id;
                updateExperimentInState(data.experiment);
                state.selectedExperimentId = data.experiment.id;
                state.experimentDetail = data.experiment;
                if (data.experiment_item) {
                    if (sameExperiment) {
                        const index = state.experimentItems.findIndex((item) => item.id === data.experiment_item.id);
                        if (index >= 0) {
                            state.experimentItems[index] = data.experiment_item;
                        } else {
                            state.experimentItems.unshift(data.experiment_item);
                        }
                    } else {
                        state.experimentItems = [data.experiment_item];
                    }
                }
                loadExperimentDetail(data.experiment.id, false).catch(() => {});
            }
            applyStrategyV2Response(data, data.strategy_version?.id || data.version?.id || '');
            showToast('Đã chạy nghiên cứu thử và tạo bản nháp chiến lược');
            activateTab('strategy-versions');
            renderStrategyV2Manager();
        } finally {
            state.researchMockRunning = false;
            renderAIJobs();
        }
    }

    function renderAutoResearch() {
        const symbolSelect = $('#autoResearchSymbolSelect');
        if (!symbolSelect) return;
        const modeSelect = $('#autoResearchModeSelect');
        const mode = modeSelect?.value || 'from_zero';
        const methodSelect = $('#autoResearchMethodSelect');
        const methodProfile = methodSelect?.value || 'h4_trend_zone_m15_pa';
        renderAutoResearchMethodGuide(methodProfile);
        renderAutoResearchMethodChecklist(methodProfile, mode);
        renderAutoResearchTournamentBoard(state.lastAutoResearchTournament);
        const openAiReady = state.openAiSettings?.ready === true && state.openAiSettings?.budget?.allowed !== false;
        const aiProviderSelect = $('#autoResearchAiProviderSelect');
        if (aiProviderSelect && !state.autoResearchAiProviderTouched) {
            aiProviderSelect.value = openAiReady ? 'openai_chat' : 'internal';
        }
        const aiProvider = aiProviderSelect?.value || (openAiReady ? 'openai_chat' : 'internal');
        const providerNote = $('#autoResearchAiProviderNote');
        if (providerNote) {
            providerNote.textContent = aiProvider === 'openai_chat'
                ? openAiReady
                    ? `OpenAI đã sẵn sàng. Còn lại hôm nay: ${formatUsd(state.openAiSettings?.budget?.remaining_usd || 0)}.`
                    : 'OpenAI chưa sẵn sàng hoặc đã hết ngân sách ngày. Vào mục Kết nối OpenAI để kiểm tra.'
                : openAiReady
                    ? 'Đang dùng AI nội bộ theo lựa chọn hiện tại. Có thể đổi lại OpenAI API nếu muốn dùng key.'
                    : 'OpenAI chưa sẵn sàng nên tạm dùng AI nội bộ. Không gọi API và không tốn chi phí OpenAI.';
        }

        const availableSymbols = autoResearchSymbols();
        let selectedSymbols = autoResearchSelectedSymbols().filter((symbol) => availableSymbols.includes(symbol));
        if (!state.autoResearchSymbolsTouched && !selectedSymbols.length && availableSymbols.length) {
            selectedSymbols = [autoResearchDefaultSymbol()];
        }
        state.autoResearchSelectedSymbols = selectedSymbols;
        symbolSelect.innerHTML = autoResearchSymbols()
            .map((symbol) => `<option value="${escapeAttr(symbol)}" ${selectedSymbols.includes(symbol) ? 'selected' : ''}>${escapeHtml(symbol)}</option>`)
            .join('');
        renderAutoResearchSymbolChecklist(availableSymbols, selectedSymbols);
        const activeSymbols = autoResearchSelectedSymbols();

        const versionSelect = $('#autoResearchVersionSelect');
        if (versionSelect) {
            const currentVersion = mode === 'from_zero' ? '' : (versionSelect.value || state.selectedStrategyVersionId || state.strategyVersions[0]?.id || '');
            versionSelect.innerHTML = '<option value="">Dùng version mới nhất</option>'
                + (state.strategyVersions || []).map((version) => `
                    <option value="${escapeAttr(version.id)}" ${version.id === currentVersion ? 'selected' : ''}>
                        ${escapeHtml(displayStrategyVersionName(version.name || version.id))} | ${escapeHtml(translateStatus(version.status || ''))}
                    </option>
                `).join('');
            if ([...versionSelect.options].some((option) => option.value === currentVersion)) {
                versionSelect.value = currentVersion;
            }
            versionSelect.disabled = mode === 'from_zero';
            versionSelect.closest('.field')?.classList.toggle('is-muted', mode === 'from_zero');
        }

        const button = $('#runAutoResearchBtn');
        if (button) {
            const methods = autoResearchMethodsForRun(mode);
            const tournamentEnabled = autoResearchTournamentEnabled() && mode === 'from_zero';
            button.disabled = state.autoResearchRunning || !activeSymbols.length || (aiProvider === 'openai_chat' && !openAiReady);
            button.textContent = state.autoResearchRunning
                ? 'Đang chạy research...'
                : tournamentEnabled
                    ? `Chạy giải đấu ${number(methods.length)} phương pháp`
                    : (mode === 'from_zero' ? 'Bắt đầu tự tìm chiến lược' : 'Chạy tối ưu chiến lược');
        }
        const stopButton = $('#stopAutoResearchBtn');
        if (stopButton) {
            stopButton.hidden = !state.autoResearchRunning;
            stopButton.disabled = !!state.autoResearchCancelRequested;
            stopButton.textContent = state.autoResearchCancelRequested ? 'Đang dừng...' : 'Dừng sau lượt này';
        }
        const refreshButton = $('#refreshAutoResearchBtn');
        if (refreshButton) {
            refreshButton.disabled = state.autoResearchRunning;
        }

        const latest = state.lastAutoResearchRun || state.autoResearchRuns?.[0] || null;
        const status = $('#autoResearchStatus');
        if (status) {
            const batch = state.autoResearchBatch;
            status.textContent = state.autoResearchRunning
                ? autoResearchRunningStatusText()
                : latest
                    ? `${latest.symbol || ''} | ${autoResearchModeLabel(latest.mode || '')} | ${translateStatus((latest.status || '').toLowerCase()) || latest.status} | ${latest.stopReason || ''}`
                    : state.autoResearchError
                        ? `Lỗi AI Research: ${state.autoResearchError}`
                        : aiProvider === 'openai_chat' && !openAiReady
                        ? 'OpenAI chưa sẵn sàng hoặc đã hết ngân sách ngày. Hãy kiểm tra mục Kết nối OpenAI.'
                        : 'Chọn chế độ và một hoặc nhiều cặp tiền rồi bấm chạy. Hệ thống chỉ backtest và tạo bản nghiên cứu, không đặt lệnh MT5.';
        }

        renderAutoResearchMetrics(latest);
        renderAutoResearchBrain(latest);
        renderAutoResearchRounds(latest);
        renderAutoResearchTradeAudit(latest);
        renderAutoResearchRuns();
        renderAutoResearchProgress();
    }

    function autoResearchMethodProfiles() {
        return {
            h4_trend_zone_m15_pa: {
                label: 'H4 Trend + H4 Zone + M15 Price Action',
                summary: 'H4 đọc xu hướng/sóng và vùng cản; chỉ khi giá về vùng H4 mới xuống M15 tìm Price Action đảo chiều để vào lệnh.',
                rules: ['H4 xác định hướng BUY/SELL bằng market structure', 'H4 chọn vùng canh buy/sell hoặc hỗ trợ/kháng cự', 'M15 xác nhận rejection wick, engulfing, liquidity sweep hoặc CHoCH/BOS tại vùng', 'SL sau vùng/swing, TP theo RR hoặc vùng đối diện'],
                knobs: ['Cách đọc trend H4', 'Độ mạnh vùng H4', 'Loại PA M15', 'Tỷ lệ wick/body', 'Yêu cầu sweep/CHoCH', 'Entry biên/50% vùng', 'RR/SL/TP'],
            },
            pa_sd_h4_m15: {
                label: 'Price Action + Vùng canh buy/sell H4/M15 (rộng)',
                summary: 'Bản cũ rộng hơn: H4 tìm vùng canh buy/sell, M15 xác nhận phản ứng rồi mới vào lệnh.',
                rules: ['H4 xác định vùng canh buy/sell', 'M15 xác nhận BOS/CHoCH hoặc phản ứng tại vùng', 'SL sau vùng/swing', 'TP theo RR tối thiểu hoặc vùng đối diện'],
                knobs: ['Độ mạnh vùng', 'Số lần chạm vùng', 'BOS H4', 'Xác nhận M15', 'Entry biên vùng/50%', 'RR', 'BE'],
            },
            liquidity_sweep_pa: {
                label: 'Liquidity sweep / false break',
                summary: 'Ưu tiên cú quét thanh khoản hoặc phá giả tại vùng canh buy/sell rồi chờ M15 xác nhận đảo chiều.',
                rules: ['Giá quét đỉnh/đáy hoặc xuyên vùng rồi hồi lại', 'Ưu tiên entry sát biên vùng', 'M15 cần phản ứng rõ', 'Không ưu tiên vùng quá nhiễu'],
                knobs: ['Fake break/QML', 'Entry extreme', 'Thời gian chờ limit', 'Độ mạnh vùng', 'RR', 'BE'],
            },
            trend_pullback: {
                label: 'Pullback theo xu hướng',
                summary: 'Chỉ tìm điểm hồi về vùng thuận xu hướng, dùng M15 để xác nhận xu hướng tiếp diễn.',
                rules: ['H4 có BOS/xu hướng rõ', 'Hạn chế ngược trend', 'M15 phá cấu trúc theo trend', 'Tránh sideway bẩn'],
                knobs: ['BOS H4', 'Min zone strength', 'Sideway filter', 'Entry 50% vùng', 'RR', 'BE'],
            },
            range_sd_reversal: {
                label: 'Range trading tại vùng canh buy/sell',
                summary: 'Tập trung hai biên range, mua demand và bán supply khi chưa có phá cấu trúc rõ.',
                rules: ['Không yêu cầu trend quá mạnh', 'Cho vùng sống lâu hơn', 'Entry tại biên/50% vùng', 'Tập trung đủ mẫu để lọc range tốt/xấu'],
                knobs: ['Vòng đời vùng', 'Số lần chạm', 'Entry', 'Sideway filter', 'RR', 'Thời gian chờ'],
            },
            free_explore: {
                label: 'Tự do khám phá nhiều họ chiến lược',
                summary: 'Nới khung ban đầu để AI dò thêm nhiều biến thể khi phương pháp hiện tại liên tục kém.',
                rules: ['Không khóa quá chặt một kiểu chiến lược', 'Ưu tiên đủ mẫu trước', 'Sau đó siết lại bằng KPI', 'Dùng khi PA + S/D không cho tín hiệu tốt'],
                knobs: ['Kiểu chiến lược', 'Độ mạnh vùng', 'M15 confirmation', 'Entry', 'RR', 'Bộ lọc nhiễu'],
            },
        };
    }

    function autoResearchMethodLabel(methodProfile) {
        return autoResearchMethodProfiles()[String(methodProfile || '')]?.label || 'H4 Trend + H4 Zone + M15 Price Action';
    }

    function renderAutoResearchMethodGuide(methodProfile) {
        const target = $('#autoResearchMethodGuide');
        if (!target) return;
        const profile = autoResearchMethodProfiles()[methodProfile] || autoResearchMethodProfiles().h4_trend_zone_m15_pa;
        target.innerHTML = `
            <div>
                <strong>${escapeHtml(profile.label)}</strong>
                <span>${escapeHtml(profile.summary)}</span>
            </div>
            <div>
                <strong>Luật đo được</strong>
                <span>${profile.rules.map((item) => escapeHtml(item)).join(' · ')}</span>
            </div>
            <div>
                <strong>AI được tối ưu</strong>
                <span>${profile.knobs.map((item) => escapeHtml(item)).join(' · ')}</span>
            </div>
        `;
    }

    function autoResearchTournamentEnabled() {
        const toggle = $('#autoResearchTournamentToggle');
        return toggle ? toggle.checked : false;
    }

    function autoResearchDefaultTournamentMethods(currentMethod = 'h4_trend_zone_m15_pa') {
        return Array.from(new Set([
            currentMethod || 'h4_trend_zone_m15_pa',
            'liquidity_sweep_pa',
            'trend_pullback',
            'range_sd_reversal',
        ])).filter((method) => autoResearchMethodProfiles()[method]);
    }

    function autoResearchSelectedMethods(currentMethod = 'h4_trend_zone_m15_pa') {
        const available = autoResearchMethodProfiles();
        let selected = (state.autoResearchSelectedMethods || []).filter((method) => available[method]);
        if (!state.autoResearchMethodsTouched || !selected.length) {
            selected = autoResearchDefaultTournamentMethods(currentMethod);
        }
        return selected;
    }

    function autoResearchMethodsForRun(mode = 'from_zero') {
        const currentMethod = $('#autoResearchMethodSelect')?.value || 'h4_trend_zone_m15_pa';
        if (mode !== 'from_zero' || !autoResearchTournamentEnabled()) {
            return [currentMethod];
        }
        return autoResearchSelectedMethods(currentMethod);
    }

    function renderAutoResearchMethodChecklist(currentMethod = 'h4_trend_zone_m15_pa', mode = 'from_zero') {
        const target = $('#autoResearchMethodChecklist');
        const count = $('#autoResearchMethodCount');
        const toggle = $('#autoResearchTournamentToggle');
        if (!target) return;
        const disabled = mode !== 'from_zero';
        const selected = new Set(autoResearchSelectedMethods(currentMethod));
        const profiles = autoResearchMethodProfiles();
        target.innerHTML = Object.entries(profiles).map(([key, profile]) => `
            <label class="method-check${selected.has(key) ? ' is-checked' : ''}${disabled ? ' is-disabled' : ''}">
                <input type="checkbox" value="${escapeAttr(key)}" data-auto-research-method ${selected.has(key) ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
                <span>${escapeHtml(profile.label)}</span>
            </label>
        `).join('');
        if (toggle) {
            toggle.disabled = disabled;
            toggle.closest('.method-tournament-card')?.classList.toggle('is-disabled', disabled);
        }
        if (count) {
            count.textContent = disabled
                ? 'Giải đấu chỉ dùng khi tự sinh từ số 0'
                : `${number(selected.size)} phương pháp được chọn`;
        }
        $$('[data-auto-research-method]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                state.autoResearchMethodsTouched = true;
                const checked = $$('[data-auto-research-method]:checked').map((item) => item.value).filter(Boolean);
                state.autoResearchSelectedMethods = checked.length ? checked : [currentMethod];
                renderAutoResearch();
            });
        });
    }

    function renderAutoResearchTournamentBoard(tournament) {
        const target = $('#autoResearchTournamentBoard');
        if (!target) return;
        if (!tournament?.leaderboard?.length) {
            target.innerHTML = '';
            return;
        }
        target.innerHTML = `
            <section class="panel-subsection method-tournament-results">
                <div class="panel-header compact">
                    <div>
                        <h2>Bảng xếp hạng phương pháp</h2>
                        <span>${number(tournament.results?.length || 0)} lượt chạy | ${escapeHtml(tournament.createdAt || '')}</span>
                    </div>
                    <div class="topbar-actions">
                        <button class="button secondary" type="button" data-tournament-top3>Chạy sâu top 3</button>
                    </div>
                </div>
                ${tournament.methodSummary?.length ? `
                    <div class="method-summary-grid">
                        ${tournament.methodSummary.map((item, index) => methodSummaryCardHtml(item, index)).join('')}
                    </div>
                ` : ''}
                <div class="method-ranking-table">
                    <div class="method-ranking-head">
                        <span>#</span><span>Phương pháp</span><span>Cặp</span><span>Lệnh</span><span>WR</span><span>RR</span><span>PF</span><span>DD</span><span>Xếp hạng</span><span>Gợi ý</span>
                    </div>
                    ${tournament.leaderboard.map((item, index) => methodRankingRowHtml(item, index)).join('')}
                </div>
            </section>
        `;
        $$('[data-tournament-run-id]').forEach((button) => {
            button.addEventListener('click', () => {
                const run = (state.autoResearchRuns || []).find((item) => item.id === button.dataset.tournamentRunId)
                    || tournament.results.find((item) => item.id === button.dataset.tournamentRunId);
                prepareAutoResearchDeepDive(run, Number(run?.bestCandidate?.round || 0));
            });
        });
        $('[data-tournament-top3]')?.addEventListener('click', () => {
            const top = tournament.leaderboard.slice(0, 3).map((item) => item.run).filter(Boolean);
            if (!top.length) {
                showToast('Chưa có bản top để chạy sâu.');
                return;
            }
            const best = top[0];
            prepareAutoResearchDeepDive(best, Number(best.bestCandidate?.round || 0));
            showToast('Đã chuẩn bị chạy sâu bản đang xếp hạng cao nhất. Các bản top còn lại nằm trong bảng để chọn tiếp.');
        });
    }

    function methodSummaryCardHtml(item, index) {
        const run = item.bestRun || {};
        const summary = run.bestCandidate?.summary || {};
        return `
            <article class="method-summary-card${index === 0 ? ' is-best' : ''}">
                <div>
                    <small>${index === 0 ? 'Đang sáng nhất' : `${number(index + 1)}. Theo phương pháp`}</small>
                    <strong>${escapeHtml(item.label || autoResearchMethodLabel(item.method || ''))}</strong>
                </div>
                <div class="method-summary-stats">
                    <span>Điểm TB <b>${formatFixed(item.averageScore || 0, 2)}</b></span>
                    <span>Tốt nhất <b>${escapeHtml(run.symbol || '')}</b></span>
                    <span>${number(item.passed || 0)}/${number(item.runs || 0)} đạt KPI</span>
                </div>
                <div class="method-summary-foot">
                    <span>${escapeHtml(item.verdict || '')}</span>
                    ${run.bestStrategyVersionId ? `<button class="button secondary" type="button" data-tournament-run-id="${escapeAttr(run.id || '')}">Chạy tiếp</button>` : ''}
                </div>
            </article>
        `;
    }

    function methodRankingRowHtml(item, index) {
        const run = item.run || {};
        const summary = run.bestCandidate?.summary || {};
        const verdict = item.verdict || autoResearchTournamentVerdict(run);
        return `
            <div class="method-ranking-row${index === 0 ? ' is-best' : ''}">
                <span>${number(index + 1)}</span>
                <span>${escapeHtml(run.methodProfileLabel || autoResearchMethodLabel(run.methodProfile || ''))}</span>
                <span>${escapeHtml(run.symbol || '')}</span>
                <span>${number(summary.totalTrades || 0)}</span>
                <span>${formatFixed(summary.winRate || 0, 2)}%</span>
                <span>${formatFixed(summary.averageRR || 0, 2)}</span>
                <span>${formatFixed(summary.profitFactor || 0, 2)}</span>
                <span>${formatFixed(summary.maxDrawdownPercent || 0, 2)}%</span>
                <span>${formatFixed(item.rankScore || 0, 2)}</span>
                <span>
                    ${escapeHtml(verdict)}
                    ${run.bestStrategyVersionId ? `<button class="button secondary" type="button" data-tournament-run-id="${escapeAttr(run.id || '')}">Chạy tiếp</button>` : ''}
                </span>
            </div>
        `;
    }

    function buildAutoResearchTournament(results, symbols, methods) {
        const runs = results.map((item) => item?.research_run).filter(Boolean);
        const leaderboard = runs
            .map((run) => ({
                run,
                rankScore: autoResearchTournamentRankScore(run),
                verdict: autoResearchTournamentVerdict(run),
            }))
            .sort((a, b) => Number(b.rankScore || 0) - Number(a.rankScore || 0));
        const methodGroups = leaderboard.reduce((groups, item) => {
            const method = item.run?.methodProfile || 'h4_trend_zone_m15_pa';
            if (!groups[method]) groups[method] = [];
            groups[method].push(item);
            return groups;
        }, {});
        const methodSummary = Object.entries(methodGroups)
            .map(([method, items]) => {
                const sorted = [...items].sort((a, b) => Number(b.rankScore || 0) - Number(a.rankScore || 0));
                const averageScore = items.reduce((sum, item) => sum + Number(item.rankScore || 0), 0) / Math.max(1, items.length);
                const passed = items.filter((item) => item.run?.status === 'PASSED').length;
                const bestItem = sorted[0] || {};
                return {
                    method,
                    label: autoResearchMethodLabel(method),
                    runs: items.length,
                    passed,
                    averageScore: Math.round(averageScore * 100) / 100,
                    bestRun: bestItem.run || null,
                    bestScore: bestItem.rankScore || 0,
                    verdict: autoResearchMethodGroupVerdict(bestItem.run, passed, items.length),
                };
            })
            .sort((a, b) => Number(b.averageScore || 0) - Number(a.averageScore || 0));
        return {
            id: `tournament_${Date.now()}`,
            createdAt: new Date().toLocaleString(),
            symbols,
            methods,
            results: runs,
            leaderboard,
            methodSummary,
        };
    }

    function autoResearchMethodGroupVerdict(bestRun, passed, total) {
        if (passed > 0) return 'Có lượt đạt KPI, nên kiểm tra sâu và chống overfit.';
        const summary = bestRun?.bestCandidate?.summary || {};
        const trades = Number(summary.totalTrades || 0);
        if (!trades) return 'Chưa tạo được lệnh đủ rõ, nên tạm để sau.';
        if ((summary.profitFactor || 0) >= 1 && trades >= 20) return 'Có mẫu giao dịch, nên chạy thêm trên nhiều cặp.';
        if (trades < 10) return 'Tín hiệu quá hiếm, chưa đáng tin.';
        if ((summary.averageRR || 0) < 0) return 'Có lệnh nhưng RR yếu, cần sửa entry hoặc SL/TP.';
        return `${number(passed)}/${number(total)} lượt đạt KPI, cần thêm dữ liệu để kết luận.`;
    }

    function autoResearchTournamentRankScore(run) {
        const best = run?.bestCandidate || {};
        const summary = best.summary || {};
        const kpi = run?.kpi || {};
        const trades = Number(summary.totalTrades || 0);
        const tradeMin = Math.max(1, Number(kpi.totalTradesMin || 30));
        const sampleRatio = Math.min(1, trades / tradeMin);
        let score = Number(best.score || 0);
        score += sampleRatio * 25;
        if (trades < Math.ceil(tradeMin * 0.35)) score -= 35;
        if ((summary.profitFactor || 0) < 1) score -= 18;
        if ((summary.averageRR || 0) < 0) score -= 15;
        if ((summary.maxDrawdownPercent || 0) > (kpi.maxDrawdownMax || 10)) score -= 20;
        if (run?.status === 'PASSED') score += 60;
        return Math.round(score * 100) / 100;
    }

    function autoResearchTournamentVerdict(run) {
        const summary = run?.bestCandidate?.summary || {};
        const kpi = run?.kpi || {};
        const tradeMin = Number(kpi.totalTradesMin || 30);
        if (run?.status === 'PASSED') return 'Đạt KPI, cần kiểm tra sâu';
        if ((summary.totalTrades || 0) < Math.ceil(tradeMin * 0.35)) return 'Quá ít lệnh, chưa tin';
        if ((summary.profitFactor || 0) < 1 && (summary.winRate || 0) < 40) return 'Đang yếu, tạm loại';
        if ((summary.averageRR || 0) < (kpi.averageRRMin || 2)) return 'Có mẫu, cần cải thiện RR';
        if ((summary.winRate || 0) < (kpi.winRateMin || 50)) return 'Có mẫu, cần tăng chất lượng';
        return 'Có tiềm năng, nên chạy tiếp';
    }

    function renderAutoResearchMetrics(run) {
        const target = $('#autoResearchMetrics');
        if (!target) return;
        const best = run?.bestCandidate;
        const summary = best?.summary;
        if (!summary) {
            target.innerHTML = state.autoResearchError
                ? `<section class="panel-subsection research-result-card"><div class="notice danger">AI Research bị lỗi: ${escapeHtml(state.autoResearchError)}</div></section>`
                : `<section class="panel-subsection research-result-card is-empty">
                    <div class="workflow-stage-head">
                        <span class="stage-index">3</span>
                        <div>
                            <h2>Chưa có kết quả</h2>
                            <p>Chọn phạm vi ở trên rồi bấm chạy. Khi có kết quả, phần này sẽ chỉ ra bản tốt nhất và bước tiếp theo.</p>
                        </div>
                    </div>
                </section>`;
            return;
        }

        const bestRound = bestAutoResearchRound(run);
        const statusText = run.status === 'PASSED' ? 'Đạt KPI' : 'Chưa đạt KPI';
        const statusClass = run.status === 'PASSED' ? 'is-good' : 'is-warn';
        const failReasons = best.failReasons || bestRound?.failReasons || [];
        const mainIssue = failReasons[0] || run.stopReason || 'Chưa có lỗi nổi bật.';
        target.innerHTML = `
            <section class="panel-subsection research-result-card">
                <div class="workflow-stage-head">
                    <span class="stage-index">3</span>
                    <div>
                        <h2>Kết quả nhanh</h2>
                        <p>${escapeHtml(run.symbol || '')} | ${escapeHtml(run.methodProfileLabel || autoResearchMethodLabel(run.methodProfile || ''))} | ${number(run.roundsRun || 0)} vòng</p>
                    </div>
                    <span class="workflow-pill ${statusClass}">${escapeHtml(statusText)}</span>
                </div>
                <div class="backtest-summary-grid compact research-kpi-grid">
                    ${backtestMetricHtml('Bản tốt nhất', bestRound ? `Vòng ${number(bestRound.round)} - ${autoResearchFamilyLabel(bestRound.strategyFamily || '')}` : 'Chưa có')}
                    ${backtestMetricHtml('Số lệnh', number(summary.totalTrades || 0))}
                    ${backtestMetricHtml('Winrate', `${formatFixed(summary.winRate || 0, 2)}%`)}
                    ${backtestMetricHtml('Average RR', formatFixed(summary.averageRR || 0, 2))}
                    ${backtestMetricHtml('Profit factor', formatFixed(summary.profitFactor || 0, 2))}
                    ${backtestMetricHtml('Max DD', `${formatFixed(summary.maxDrawdownPercent || 0, 2)}%`)}
                </div>
                <div class="research-next-strip">
                    <div>
                        <strong>Vướng chính</strong>
                        <span>${escapeHtml(mainIssue)}</span>
                    </div>
                    <div>
                        <strong>Nên làm tiếp</strong>
                        <span>${escapeHtml(autoResearchNextAction(run, bestRound))}</span>
                    </div>
                </div>
            </section>
        `;
    }

    function renderAutoResearchRounds(run) {
        const target = $('#autoResearchRounds');
        if (!target) return;
        const rounds = run?.rounds || [];
        if (!rounds.length) {
            target.innerHTML = '';
            return;
        }

        const items = rounds.map((round) => {
            const summary = round.summary || {};
            const diagnostics = round.diagnostics || {};
            const fail = (round.failReasons || []).join('; ') || 'Không có';
            const focus = round.optimizationFocus || 'Chưa có ghi chú tối ưu cho vòng này.';
            const changes = (round.changes || []).slice(0, 6).map((change) =>
                `${formatDisplayLabel(change.field || '', 'ruleFields')}: ${String(change.oldValue ?? '')} → ${String(change.newValue ?? '')}`
            ).join(' | ') || 'Baseline';
            const trades = Array.isArray(round.trades) ? round.trades : [];
            return `
                <details class="research-round-item">
                    <summary>
                        <strong>Vòng ${number(round.round)} | ${escapeHtml(round.candidateName || '')}</strong>
                        <span>${round.kpiPassed ? 'Đạt KPI' : 'Chưa đạt'} | ${escapeHtml(autoResearchFamilyLabel(round.strategyFamily || ''))} | score ${formatFixed(round.score || 0, 2)} | ${number(summary.totalTrades || trades.length || 0)} lệnh | WR ${formatFixed(summary.winRate || 0, 2)}%</span>
                    </summary>
                    <div class="memory-summary">
                        ${memoryRow('AI đang tối ưu', focus)}
                        ${memoryRow('Kiểu chiến lược', autoResearchFamilyLabel(round.strategyFamily || ''))}
                        ${memoryRow('Score dùng để xếp hạng', `${formatFixed(round.score || 0, 2)} - điểm nội bộ gộp lợi nhuận, winrate, RR, profit factor, số lệnh, drawdown và lỗi KPI. Score chỉ để chọn vòng tốt nhất, không phải tỷ lệ thắng.`)}
                        ${memoryRow('Khoảng dữ liệu', `${summary.startDate || ''} → ${summary.endDate || ''}`)}
                        ${memoryRow('KPI fail', fail)}
                        ${memoryRow('Lý do bỏ qua nhiều nhất', diagnostics.topSkippedReason ? `${translateReasonCode(diagnostics.topSkippedReason)} (${number(diagnostics.topSkippedCount || 0)})` : 'Không có')}
                        ${memoryRow('Pending tạo / khớp', `${number(diagnostics.pendingLimitCreated || 0)} / ${number(diagnostics.pendingLimitFilled || 0)}`)}
                        ${memoryRow('Rule đã chỉnh', changes)}
                    </div>
                    <div class="research-round-actions">
                        <button class="button secondary" type="button" data-auto-research-round="${number(round.round)}">Xem ${number(trades.length || summary.totalTrades || 0)} lệnh</button>
                    </div>
                </details>
            `;
        }).join('');

        target.innerHTML = `
            <details class="workflow-details research-round-log">
                <summary>
                    <span>Nhật ký kỹ thuật từng vòng</span>
                    <small>${number(rounds.length)} vòng đã thử. Mở khi muốn xem AI đã đổi rule gì và vì sao vòng đó bị loại.</small>
                </summary>
                <div class="research-round-log-body">${items}</div>
            </details>
        `;

        $$('[data-auto-research-round]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                state.selectedAutoResearchRound = Number(button.dataset.autoResearchRound || 0);
                renderAutoResearchTradeAudit(run);
            });
        });
    }

    function autoResearchTuningAdviceHtml(run) {
        const rounds = run?.rounds || [];
        if (!rounds.length) return '';
        const last = rounds[rounds.length - 1] || {};
        const best = run.bestCandidate || {};
        const bestSummary = best.summary || {};
        const lastFocus = last.optimizationFocus || '';
        const fail = (best.failReasons || last.failReasons || []).join('; ') || 'Không có lỗi KPI rõ.';
        const note = run.status === 'PASSED'
            ? 'Đã có bản đạt KPI. Vẫn nên xem bảng lệnh và chạy thêm out-of-sample/demo trước khi dùng thật.'
            : `Chưa đạt KPI. Vòng sau sẽ ưu tiên theo lỗi còn lại: ${fail}`;
        return `
            <div class="notice research-tuning-note">
                <strong>Trạng thái tối ưu:</strong>
                <span>${escapeHtml(note)}</span>
                <span>Best hiện tại: ${number(bestSummary.totalTrades || 0)} lệnh, WR ${formatFixed(bestSummary.winRate || 0, 2)}%, RR ${formatFixed(bestSummary.averageRR || 0, 2)}, PF ${formatFixed(bestSummary.profitFactor || 0, 2)}.</span>
                ${lastFocus ? `<span>Vòng gần nhất: ${escapeHtml(lastFocus)}</span>` : ''}
            </div>
        `;
    }

    function renderAutoResearchBrain(run) {
        const target = $('#autoResearchBrain');
        if (!target) return;
        const rounds = run?.rounds || [];
        if (!run || !rounds.length) {
            target.innerHTML = '';
            return;
        }

        const methodLabel = run.methodProfileLabel || autoResearchMethodLabel(run.methodProfile || $('#autoResearchMethodSelect')?.value || 'h4_trend_zone_m15_pa');
        const bestRoundNumber = Number(run.bestCandidate?.round || 0);
        const bestRound = rounds.find((round) => Number(round.round) === bestRoundNumber) || rounds[0];
        const conclusion = autoResearchBrainConclusion(run, bestRound);
        const rejected = autoResearchRejectedRounds(run);
        const candidates = autoResearchPromisingRounds(run);

        target.innerHTML = `
            <section class="panel-subsection research-decision-panel">
                <div class="research-decision-head">
                    <div>
                        <span class="workflow-eyebrow">Bước 3 - quyết định</span>
                        <h2>Nên làm gì với kết quả này?</h2>
                        <p>${escapeHtml(methodLabel)} | ${escapeHtml(run.symbol || '')}</p>
                    </div>
                    <div class="topbar-actions research-decision-actions">
                        <button class="button secondary" type="button" data-auto-research-switch-method="${escapeAttr(run.methodProfile || 'h4_trend_zone_m15_pa')}">Thử phương pháp khác</button>
                        <button class="button" type="button" data-auto-research-deepen-round="${escapeAttr(bestRound?.round || '')}">Chạy tiếp từ bản tốt nhất</button>
                    </div>
                </div>
                <div class="research-decision-grid">
                    <article>
                        <small>Kết luận</small>
                        <strong>${escapeHtml(conclusion)}</strong>
                    </article>
                    <article>
                        <small>Bản tốt nhất</small>
                        <strong>${bestRound ? `Vòng ${number(bestRound.round)} | ${escapeHtml(autoResearchFamilyLabel(bestRound.strategyFamily || ''))}` : 'Chưa có'}</strong>
                    </article>
                    <article>
                        <small>Hành động tiếp theo</small>
                        <strong>${escapeHtml(autoResearchNextAction(run, bestRound))}</strong>
                    </article>
                </div>
                <details class="research-brain-section workflow-details-inner">
                    <summary>Bảng tiến bộ theo vòng</summary>
                    ${autoResearchProgressTableHtml(rounds, bestRoundNumber)}
                </details>
                <details class="research-brain-section workflow-details-inner">
                    <summary>Ứng viên nên giữ và hướng nên bỏ</summary>
                    <div class="research-brain-grid">
                        <section>
                            <h3>Bản có tiềm năng</h3>
                            ${autoResearchCandidateListHtml(candidates, run)}
                        </section>
                        <section>
                            <h3>Hướng đang kém</h3>
                            ${autoResearchRejectedListHtml(rejected)}
                        </section>
                    </div>
                </details>
                <details class="research-brain-section workflow-details-inner">
                    <summary>Kỹ thuật: cây sinh vòng và bộ nhớ gần đây</summary>
                    ${autoResearchLineageHtml(rounds, bestRoundNumber)}
                    ${autoResearchMemoryBySymbolHtml(state.autoResearchRuns || [])}
                </details>
            </section>
        `;

        $$('[data-auto-research-deepen-round]').forEach((button) => {
            button.addEventListener('click', () => prepareAutoResearchDeepDive(run, Number(button.dataset.autoResearchDeepenRound || 0)));
        });
        $$('[data-auto-research-switch-method]').forEach((button) => {
            button.addEventListener('click', () => switchAutoResearchMethod(button.dataset.autoResearchSwitchMethod || 'h4_trend_zone_m15_pa'));
        });
    }

    function autoResearchBrainConclusion(run, bestRound) {
        const best = bestRound?.summary || run.bestCandidate?.summary || {};
        const fail = run.bestCandidate?.failReasons || bestRound?.failReasons || [];
        if (run.status === 'PASSED') {
            return `Đã tìm được bản đạt KPI trên dữ liệu backtest. Cần xem bảng lệnh rồi kiểm tra validation/demo trước khi dùng thật.`;
        }
        if ((best.totalTrades || 0) < (run.kpi?.totalTradesMin || 30)) {
            return `Chất lượng có thể nhìn ổn nhưng mẫu còn quá ít: ${number(best.totalTrades || 0)} lệnh. Chưa đủ tin cậy để kết luận phương pháp thắng.`;
        }
        if ((best.winRate || 0) < (run.kpi?.winRateMin || 50) && (best.profitFactor || 0) < (run.kpi?.profitFactorMin || 1.5)) {
            return `Đã có mẫu nhưng winrate/PF còn thấp. Phương pháp hiện tại chưa chứng minh được lợi thế trên ${run.symbol || 'cặp này'}.`;
        }
        if ((best.averageRR || 0) < (run.kpi?.averageRRMin || 2)) {
            return `Có tín hiệu nhưng RR trung bình chưa đạt. Cần tối ưu vị trí entry, SL/TP hoặc cách dời BE.`;
        }
        return fail.length ? `Chưa đạt KPI: ${fail.join('; ')}` : 'Chưa đạt KPI nhưng chưa có lỗi trội rõ. Nên chạy thêm vài vòng hoặc thử phương pháp khác để so sánh.';
    }

    function autoResearchNextAction(run, bestRound) {
        const best = bestRound?.summary || {};
        const tradeMin = run.kpi?.totalTradesMin || 30;
        if (run.status === 'PASSED') return 'Mở bảng lệnh, xem chart từng lệnh, sau đó chạy validation/final test hoặc demo.';
        if ((best.totalTrades || 0) < tradeMin) return 'Giữ phương pháp nhưng nới điều kiện có kiểm soát để tăng số mẫu.';
        if ((best.winRate || 0) < (run.kpi?.winRateMin || 50)) return 'Siết chất lượng vùng/xác nhận M15; nếu 2-3 run vẫn kém thì thử phương pháp khác.';
        if ((best.averageRR || 0) < (run.kpi?.averageRRMin || 2)) return 'Chạy tiếp từ bản tốt nhất và tập trung tối ưu entry/SL/TP.';
        return 'Chạy tiếp từ bản tốt nhất thêm 20 vòng để kiểm tra có cải thiện ổn định không.';
    }

    function autoResearchProgressTableHtml(rounds, bestRoundNumber) {
        return `
            <div class="research-progress-table">
                <div class="research-progress-head">
                    <span>Vòng</span><span>Kiểu thử</span><span>Lệnh</span><span>WR</span><span>RR</span><span>PF</span><span>DD</span><span>Score</span>
                </div>
                ${rounds.map((round) => {
                    const s = round.summary || {};
                    const best = Number(round.round) === Number(bestRoundNumber) ? ' is-best' : '';
                    return `
                        <div class="research-progress-row${best}">
                            <span>${number(round.round)}</span>
                            <span>${escapeHtml(autoResearchFamilyLabel(round.strategyFamily || ''))}</span>
                            <span>${number(s.totalTrades || 0)}</span>
                            <span>${formatFixed(s.winRate || 0, 2)}%</span>
                            <span>${formatFixed(s.averageRR || 0, 2)}</span>
                            <span>${formatFixed(s.profitFactor || 0, 2)}</span>
                            <span>${formatFixed(s.maxDrawdownPercent || 0, 2)}%</span>
                            <span>${formatFixed(round.score || 0, 2)}</span>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function autoResearchLineageHtml(rounds, bestRoundNumber) {
        return `
            <div class="research-lineage">
                ${rounds.map((round) => {
                    const parent = round.parentRound ? `sinh từ vòng ${number(round.parentRound)}` : 'seed phương pháp mẹ';
                    const best = Number(round.round) === Number(bestRoundNumber) ? ' is-best' : '';
                    return `
                        <div class="research-lineage-item${best}">
                            <strong>Vòng ${number(round.round)} → ${escapeHtml(autoResearchFamilyLabel(round.strategyFamily || ''))}</strong>
                            <span>${escapeHtml(parent)} | ${escapeHtml(round.optimizationFocus || 'Chưa có ghi chú tối ưu')}</span>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function autoResearchRejectedRounds(run) {
        const bestScore = Number(run.bestCandidate?.score ?? -Infinity);
        return (run.rounds || [])
            .filter((round) => Number(round.round) !== Number(run.bestCandidate?.round || 0))
            .map((round) => {
                const s = round.summary || {};
                const reasons = round.failReasons?.length
                    ? round.failReasons
                    : [`Score thấp hơn best ${formatFixed(bestScore - Number(round.score || 0), 2)} điểm`];
                return { round, summary: s, reason: reasons.join('; ') };
            })
            .sort((a, b) => Number(a.round.score || 0) - Number(b.round.score || 0))
            .slice(0, 5);
    }

    function autoResearchPromisingRounds(run) {
        const tradeMin = Math.max(5, Math.ceil((run.kpi?.totalTradesMin || 30) * 0.4));
        return (run.rounds || [])
            .filter((round) => Number(round.summary?.totalTrades || 0) >= tradeMin || Number(round.round) === Number(run.bestCandidate?.round || 0))
            .sort((a, b) => Number(b.score || 0) - Number(a.score || 0))
            .slice(0, 3);
    }

    function autoResearchCandidateListHtml(candidates, run) {
        if (!candidates.length) return '<div class="notice">Chưa có bản đủ mẫu để chạy tiếp.</div>';
        return `<div class="research-brain-list">${candidates.map((round) => {
            const s = round.summary || {};
            const isBest = Number(round.round) === Number(run.bestCandidate?.round || 0);
            return `
                <div class="research-brain-card">
                    <strong>Vòng ${number(round.round)} | ${escapeHtml(autoResearchFamilyLabel(round.strategyFamily || ''))}</strong>
                    <span>${number(s.totalTrades || 0)} lệnh | WR ${formatFixed(s.winRate || 0, 2)}% | RR ${formatFixed(s.averageRR || 0, 2)} | PF ${formatFixed(s.profitFactor || 0, 2)}</span>
                    <span>${escapeHtml(round.optimizationFocus || '')}</span>
                    ${isBest ? `<button class="button secondary" type="button" data-auto-research-deepen-round="${escapeAttr(round.round)}">Chạy tiếp từ bản này</button>` : '<small>Chỉ bản tốt nhất hiện được lưu thành version để chạy tiếp.</small>'}
                </div>
            `;
        }).join('')}</div>`;
    }

    function autoResearchRejectedListHtml(items) {
        if (!items.length) return '<div class="notice">Chưa có hướng nào bị loại tạm thời.</div>';
        return `<div class="research-brain-list">${items.map(({ round, summary, reason }) => `
            <div class="research-brain-card">
                <strong>Vòng ${number(round.round)} | ${escapeHtml(autoResearchFamilyLabel(round.strategyFamily || ''))}</strong>
                <span>${escapeHtml(reason)}</span>
                <span>${number(summary.totalTrades || 0)} lệnh | WR ${formatFixed(summary.winRate || 0, 2)}% | RR ${formatFixed(summary.averageRR || 0, 2)} | PF ${formatFixed(summary.profitFactor || 0, 2)}</span>
            </div>
        `).join('')}</div>`;
    }

    function autoResearchMemoryBySymbolHtml(runs) {
        const latestBySymbol = new Map();
        (runs || []).forEach((run) => {
            const symbol = String(run.symbol || '').toUpperCase();
            if (!symbol || latestBySymbol.has(symbol)) return;
            latestBySymbol.set(symbol, run);
        });
        if (!latestBySymbol.size) return '<div class="notice">Chưa có bộ nhớ research gần đây.</div>';
        return `<div class="research-memory-grid">${Array.from(latestBySymbol.values()).slice(0, 8).map((run) => {
            const s = run.bestCandidate?.summary || {};
            return `
                <div class="research-memory-card">
                    <strong>${escapeHtml(run.symbol || '')}</strong>
                    <span>${escapeHtml(run.methodProfileLabel || autoResearchMethodLabel(run.methodProfile || ''))}</span>
                    <span>${escapeHtml(run.status || '')} | ${number(s.totalTrades || 0)} lệnh | WR ${formatFixed(s.winRate || 0, 2)}% | PF ${formatFixed(s.profitFactor || 0, 2)}</span>
                </div>
            `;
        }).join('')}</div>`;
    }

    function prepareAutoResearchDeepDive(run, roundNumber) {
        if (!run?.bestStrategyVersionId) {
            showToast('Chưa có bản tốt nhất để chạy tiếp. Hãy chạy research lại hoặc chọn run có best version.');
            return;
        }
        const modeSelect = $('#autoResearchModeSelect');
        if (modeSelect) modeSelect.value = 'optimize_existing';
        state.selectedStrategyVersionId = run.bestStrategyVersionId;
        setAutoResearchSelectedSymbols([run.symbol], true);
        const roundsInput = $('#autoResearchMaxRounds');
        const secondsInput = $('#autoResearchMaxSeconds');
        if (roundsInput) roundsInput.value = '20';
        if (secondsInput) secondsInput.value = '600';
        state.selectedAutoResearchRound = roundNumber || run.bestCandidate?.round || null;
        renderAutoResearch();
        showToast('Đã chuẩn bị chạy tiếp từ bản tốt nhất. Bấm chạy tối ưu chiến lược để tiếp tục.');
    }

    function switchAutoResearchMethod(currentMethod) {
        const order = ['h4_trend_zone_m15_pa', 'liquidity_sweep_pa', 'trend_pullback', 'range_sd_reversal', 'pa_sd_h4_m15', 'free_explore'];
        const currentIndex = Math.max(0, order.indexOf(currentMethod));
        const next = order[(currentIndex + 1) % order.length];
        const select = $('#autoResearchMethodSelect');
        if (select) select.value = next;
        const modeSelect = $('#autoResearchModeSelect');
        if (modeSelect) modeSelect.value = 'from_zero';
        renderAutoResearch();
        showToast(`Đã chuyển phương pháp mẹ sang: ${autoResearchMethodLabel(next)}`);
    }

    function renderAutoResearchTradeAudit(run) {
        const target = $('#autoResearchTradeAudit');
        if (!target) return;
        const rounds = run?.rounds || [];
        if (!run || !rounds.length) {
            target.innerHTML = '';
            return;
        }

        const bestRound = run.bestCandidate?.round || rounds[0]?.round || 1;
        const selectedRoundNumber = rounds.some((round) => Number(round.round) === Number(state.selectedAutoResearchRound))
            ? Number(state.selectedAutoResearchRound)
            : Number(bestRound);
        state.selectedAutoResearchRound = selectedRoundNumber;
        const round = rounds.find((item) => Number(item.round) === selectedRoundNumber) || rounds[0];
        const trades = Array.isArray(round.trades) ? round.trades : [];
        const stats = autoResearchTradeStats(trades);
        const summary = round.summary || {};

        target.innerHTML = `
            <section class="panel-subsection research-trade-panel">
                <div class="panel-header compact">
                    <div>
                        <h2>Bước 4 - Bảng lệnh</h2>
                        <span>Vòng ${number(round.round)} | ${escapeHtml(round.candidateName || '')}</span>
                    </div>
                    <div class="topbar-actions">
                        <select id="autoResearchRoundSelect" aria-label="Chọn vòng research">
                            ${rounds.map((item) => `
                                <option value="${escapeAttr(item.round)}" ${Number(item.round) === Number(round.round) ? 'selected' : ''}>
                                    Vòng ${number(item.round)} - ${number(item.trades?.length || item.summary?.totalTrades || 0)} lệnh
                                </option>
                            `).join('')}
                        </select>
                    </div>
                </div>
                <div class="backtest-summary-grid compact">
                    ${backtestMetricHtml('Tổng lệnh', number(trades.length || summary.totalTrades || 0))}
                    ${backtestMetricHtml('Thắng / thua / hòa', `${number(stats.wins)} / ${number(stats.losses)} / ${number(stats.be)}`)}
                    ${backtestMetricHtml('Winrate', `${formatFixed(stats.winRate, 2)}%`)}
                    ${backtestMetricHtml('Tổng R', formatSigned(stats.totalR, 2))}
                    ${backtestMetricHtml('Profit factor', Number.isFinite(stats.profitFactor) ? formatFixed(stats.profitFactor, 2) : '∞')}
                    ${backtestMetricHtml('Lệnh tốt nhất / xấu nhất', `${formatSigned(stats.bestR, 2)}R / ${formatSigned(stats.worstR, 2)}R`)}
                </div>
                ${trades.length ? autoResearchTradeTableHtml(run, round, trades) : '<div class="notice">Run cũ chưa lưu danh sách lệnh chi tiết. Hãy chạy research lại để có bảng audit từng lệnh.</div>'}
            </section>
        `;

        $('#autoResearchRoundSelect')?.addEventListener('change', (event) => {
            state.selectedAutoResearchRound = Number(event.target.value || 0);
            renderAutoResearchTradeAudit(run);
        });
        $$('[data-auto-research-trade-detail]').forEach((button) => {
            button.addEventListener('click', () => toggleAutoResearchTradeDetail(button));
        });
        $$('[data-auto-research-trade-chart]').forEach((button) => {
            button.addEventListener('click', () => {
                openAutoResearchTradeChart(
                    button.dataset.autoResearchRunId,
                    Number(button.dataset.autoResearchRound || 0),
                    button.dataset.autoResearchTradeId,
                    button.dataset.autoResearchTradeChart || 'M15'
                ).catch((error) => showToast(error.message));
            });
        });
    }

    function autoResearchTradeStats(trades) {
        const values = (trades || []).map((trade) => Number(trade.netR ?? trade.rrResult ?? 0));
        const wins = values.filter((value) => value > 0.05);
        const losses = values.filter((value) => value < -0.05);
        const be = Math.max(0, values.length - wins.length - losses.length);
        const grossWin = wins.reduce((sum, value) => sum + value, 0);
        const grossLoss = Math.abs(losses.reduce((sum, value) => sum + value, 0));
        return {
            wins: wins.length,
            losses: losses.length,
            be,
            winRate: values.length ? (wins.length / values.length) * 100 : 0,
            totalR: values.reduce((sum, value) => sum + value, 0),
            profitFactor: grossLoss > 0 ? grossWin / grossLoss : (grossWin > 0 ? Infinity : 0),
            bestR: values.length ? Math.max(...values) : 0,
            worstR: values.length ? Math.min(...values) : 0,
        };
    }

    function autoResearchTradeTableHtml(run, round, trades) {
        return `
            <div class="research-trade-table">
                ${trades.map((trade, index) => {
                    const netR = Number(trade.netR ?? trade.rrResult ?? 0);
                    const resultClass = netR >= 0 ? 'win' : 'loss';
                    const direction = String(trade.direction || '').toUpperCase();
                    return `
                        <div class="backtest-trade-row research-trade-row" data-auto-research-trade-row="${escapeAttr(trade.id || '')}">
                            <span class="trade-side ${direction === 'BUY' ? 'long' : 'short'}">${escapeHtml(displayDirection(direction))}</span>
                            <div class="trade-row-main">
                                <strong>#${number(index + 1)} | ${escapeHtml(translateDisplayText(trade.result || ''))} | ${escapeHtml(formatChartTime(trade.entryTime || ''))}</strong>
                                <span>${escapeHtml(formatPrice(trade.entryPrice, trade.symbol))} → ${escapeHtml(formatPrice(trade.exitPrice, trade.symbol))} | ${escapeHtml(translateDisplayText(trade.reasonEnter || trade.entryReason || ''))}</span>
                            </div>
                            <span class="trade-r ${resultClass}">${formatSigned(netR, 2)}R</span>
                            <div class="research-trade-actions">
                                <button class="button secondary" type="button" data-auto-research-trade-detail="${escapeAttr(trade.id || '')}">Chi tiết</button>
                                <button class="button secondary" type="button" data-auto-research-run-id="${escapeAttr(run.id || '')}" data-auto-research-round="${escapeAttr(round.round)}" data-auto-research-trade-id="${escapeAttr(trade.id || '')}" data-auto-research-trade-chart="M15">M15</button>
                                <button class="button secondary" type="button" data-auto-research-run-id="${escapeAttr(run.id || '')}" data-auto-research-round="${escapeAttr(round.round)}" data-auto-research-trade-id="${escapeAttr(trade.id || '')}" data-auto-research-trade-chart="H4">H4</button>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function toggleAutoResearchTradeDetail(button) {
        const row = button.closest('.research-trade-row');
        if (!row) return;
        const existing = row.querySelector('.trade-debug-detail');
        if (existing) {
            existing.remove();
            return;
        }
        const run = state.lastAutoResearchRun || state.autoResearchRuns?.[0] || null;
        const round = (run?.rounds || []).find((item) => Number(item.round) === Number(state.selectedAutoResearchRound));
        const trade = (round?.trades || []).find((item) => item.id === button.dataset.autoResearchTradeDetail);
        if (!trade) return;
        const detail = document.createElement('div');
        detail.className = 'memory-summary trade-debug-detail';
        detail.innerHTML = [
            memoryRow('Vì sao vào lệnh', translateDisplayText(trade.reasonEnter || trade.entryReason || '')),
            memoryRow('Vì sao thoát lệnh', translateDisplayText(trade.reasonExit || trade.exitReason || '')),
            memoryRow('H4 context', `${displayZone(trade.zoneType || '')} ${trade.zoneId || ''} | Trend ${trade.h4Trend || 'Không rõ'} | Độ mạnh ${trade.zoneStrength ?? 'Không có'}`),
            memoryRow('Rule/setup', `${displaySetup(trade.setupType || '')} | ${autoResearchFamilyLabel(round?.strategyFamily || '')}`),
            memoryRow('Entry / SL / TP', `${formatPrice(trade.entryPrice, trade.symbol)} / ${formatPrice(trade.initialStop ?? trade.stopLoss, trade.symbol)} / ${formatPrice(trade.targetPrice ?? trade.takeProfit, trade.symbol)}`),
            memoryRow('Kết quả', `${formatSigned(trade.netR ?? trade.rrResult ?? 0, 2)}R | ${money(trade.pnlUsd ?? trade.profitLoss ?? 0)} | giữ ${number(trade.holdingBars || 0)} nến`),
            memoryRow('MFE / MAE', `${formatFixed(trade.mfeR || 0, 2)}R / ${formatFixed(trade.maeR || 0, 2)}R`),
            memoryRow('Nhãn', (trade.tags || []).map((tag) => displayTag(tag)).join(', ') || 'Không có'),
        ].join('');
        row.appendChild(detail);
    }

    async function openAutoResearchTradeChart(runId, roundNumber, tradeId, timeframe = 'M15') {
        const run = (state.autoResearchRuns || []).find((item) => item.id === runId) || state.lastAutoResearchRun;
        const round = (run?.rounds || []).find((item) => Number(item.round) === Number(roundNumber));
        const trade = (round?.trades || []).find((item) => item.id === tradeId);
        if (!run || !round || !trade) {
            showToast('Không tìm thấy lệnh research để mở chart.');
            return;
        }

        const symbol = String(trade.symbol || run.symbol || '').toUpperCase();
        const chartTimeframe = String(timeframe || 'M15').toUpperCase();
        const file = findMarketFile(symbol, chartTimeframe);
        if (!file) {
            showToast(`${symbol} ${chartTimeframe} chưa có dữ liệu. Vào mục Dữ liệu & MT5 để tải trước.`);
            return;
        }

        state.chartView.timeframe = chartTimeframe;
        const chartRun = autoResearchRoundToBacktestRun(run, round);
        state.backtestRuns = [
            chartRun,
            ...(state.backtestRuns || []).filter((item) => (item.backtestRunId || item.id || '') !== chartRun.backtestRunId),
        ];
        state.chartView.activeRunId = chartRun.backtestRunId;
        await loadMarketData(file.file);
        state.chartView.activeRunId = chartRun.backtestRunId;
        state.chartView.highlightedZoneId = trade.zoneId || '';
        state.chartView.overlays.markers = true;
        state.chartView.overlays.slTp = true;
        state.chartView.overlays.positionBands = true;
        state.chartView.overlays.zones = true;

        if (chartTimeframe === String(chartRun.timeframe || 'M15').toUpperCase()) {
            const normalizedRun = serverRunToChartRun(chartRun);
            const chartTrade = normalizedRun.trades.find((item) => item.id === trade.id);
            if (chartTrade) {
                focusTrade(chartTrade);
            }
        } else {
            focusChartTime(timeToSeconds(trade.entryTime));
        }
        activateTab('mt5');
        showToast(`Đã mở lệnh research trên chart ${chartTimeframe}`);
    }

    function bestAutoResearchRound(run) {
        const rounds = Array.isArray(run?.rounds) ? run.rounds : [];
        if (!rounds.length) return null;
        const bestRoundNumber = Number(run?.bestCandidate?.round || 0);
        return rounds.find((round) => Number(round.round) === bestRoundNumber)
            || rounds.find((round) => Number(round.trades?.length || round.summary?.totalTrades || 0) > 0)
            || rounds[0];
    }

    async function openAutoResearchRunBestChart(run, silent = false) {
        const round = bestAutoResearchRound(run);
        if (!run || !round) return false;
        const symbol = String(run.symbol || round.summary?.symbol || '').toUpperCase();
        if (!symbol) return false;

        const preferredTimeframe = String(round.summary?.timeframe || 'M15').toUpperCase();
        const file = findMarketFile(symbol, preferredTimeframe)
            || findMarketFile(symbol, 'M15')
            || findMarketFile(symbol, 'H4');
        if (!file) {
            if (!silent) showToast(`${symbol} chưa có dữ liệu M15/H4 để mở chart research.`);
            return false;
        }

        const chartRun = autoResearchRoundToBacktestRun(run, round);
        state.backtestRuns = [
            chartRun,
            ...(state.backtestRuns || []).filter((item) => (item.backtestRunId || item.id || '') !== chartRun.backtestRunId),
        ];
        state.chartView.timeframe = file.timeframe;
        state.chartView.activeRunId = chartRun.backtestRunId;
        state.chartView.tradeFilter = 'all';
        state.chartView.overlays.markers = true;
        state.chartView.overlays.slTp = true;
        state.chartView.overlays.positionBands = true;
        state.chartView.overlays.zones = true;

        activateTab('mt5');
        await loadMarketData(file.file);
        state.chartView.activeRunId = chartRun.backtestRunId;
        state.chartView.tradeFilter = 'all';

        const activeRun = activeBacktestRun(getChartAnalytics(state.activeMarketData));
        focusBacktestRunTradeRange(activeRun);
        if (!silent) showToast(`Đã mở chart kết quả research ${symbol}.`);
        return true;
    }

    function autoResearchRoundToBacktestRun(run, round) {
        const summary = { ...(round.summary || {}) };
        if (round.diagnostics && !summary.diagnostics) {
            summary.diagnostics = round.diagnostics;
        }
        const backtestRunId = summary.backtestRunId || `${run.id}_round_${round.round}`;
        return {
            id: backtestRunId,
            backtestRunId,
            strategyVersionId: run.bestStrategyVersionId || run.baseStrategyVersionId || '',
            symbol: run.symbol || summary.symbol || '',
            timeframe: summary.timeframe || 'M15',
            contextTimeframe: summary.contextTimeframe || 'H4',
            rangeType: summary.rangeType || summary.dataSplit || 'custom',
            dataSplit: summary.dataSplit || 'custom',
            createdAt: run.completedAt || run.createdAt || '',
            summary,
            trades: round.trades || [],
            debug: null,
            source: 'auto_research',
        };
    }

    function renderAutoResearchRuns() {
        const target = $('#autoResearchRuns');
        if (!target) return;
        const runs = state.autoResearchRuns || [];
        if (!runs.length) {
            target.innerHTML = '';
            return;
        }

        target.innerHTML = `
            <details class="workflow-details research-history-details">
                <summary>
                    <span>Lịch sử research gần đây</span>
                    <small>${number(runs.length)} lần chạy. Mở khi muốn chọn lại kết quả cũ.</small>
                </summary>
                <div class="research-history-list">
                    ${runs.slice(0, 10).map((run) => {
                        const active = state.lastAutoResearchRun?.id === run.id ? ' is-active' : '';
                        const best = run.bestCandidate?.summary || {};
                        return `
                            <button class="profile-item auto-research-run-item${active}" type="button" data-auto-research-run-id="${escapeAttr(run.id)}">
                                <strong>${escapeHtml(run.symbol || '')} | ${escapeHtml(autoResearchModeLabel(run.mode || ''))} | ${escapeHtml(run.status || '')} | ${number(run.roundsRun || 0)} vòng</strong>
                                <span>Best: ${number(best.totalTrades || 0)} lệnh | WR ${formatFixed(best.winRate || 0, 2)}% | RR ${formatFixed(best.averageRR || 0, 2)} | PF ${formatFixed(best.profitFactor || 0, 2)}</span>
                                <span>${escapeHtml(run.createdAt || '')}</span>
                            </button>
                        `;
                    }).join('')}
                </div>
            </details>
        `;

        $$('.auto-research-run-item').forEach((button) => {
            button.addEventListener('click', () => {
                state.lastAutoResearchRun = (state.autoResearchRuns || []).find((run) => run.id === button.dataset.autoResearchRunId) || null;
                renderAutoResearch();
            });
        });
    }

    function autoResearchSymbols() {
        const symbols = new Set();
        (state.marketFiles || []).forEach((file) => {
            if (String(file.timeframe || '').toUpperCase() === 'M15') {
                const symbol = String(file.symbol || '').toUpperCase();
                const hasH4 = (state.marketFiles || []).some((item) => String(item.symbol || '').toUpperCase() === symbol && String(item.timeframe || '').toUpperCase() === 'H4');
                if (hasH4) symbols.add(symbol);
            }
        });
        (state.strategy?.symbols || []).forEach((symbol) => symbols.add(String(symbol || '').toUpperCase()));
        return Array.from(symbols).filter(Boolean).sort();
    }

    function renderAutoResearchSymbolChecklist(symbols, selectedSymbols) {
        const target = $('#autoResearchSymbolChecklist');
        if (!target) return;
        const selected = new Set(selectedSymbols);
        if (!symbols.length) {
            target.innerHTML = '<div class="notice">Chưa có cặp nào có đủ dữ liệu M15/H4. Hãy tải dữ liệu ở mục Dữ liệu & MT5 trước.</div>';
            renderAutoResearchSymbolCount();
            return;
        }

        target.innerHTML = symbols.map((symbol) => `
            <label class="symbol-check${selected.has(symbol) ? ' is-checked' : ''}">
                <input type="checkbox" value="${escapeAttr(symbol)}" data-auto-research-symbol ${selected.has(symbol) ? 'checked' : ''}>
                <span>${escapeHtml(symbol)}</span>
            </label>
        `).join('');

        $$('[data-auto-research-symbol]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const checked = $$('[data-auto-research-symbol]:checked').map((item) => String(item.value || '').toUpperCase()).filter(Boolean);
                setAutoResearchSelectedSymbols(checked, true);
                renderAutoResearch();
            });
        });
        renderAutoResearchSymbolCount();
    }

    function autoResearchDefaultSymbol() {
        return autoResearchSymbols()[0] || 'GBPUSD';
    }

    function autoResearchSelectedSymbols() {
        return Array.from(new Set((state.autoResearchSelectedSymbols || [])
            .map((symbol) => String(symbol || '').toUpperCase())
            .filter(Boolean)));
    }

    function setAutoResearchSelectedSymbols(symbols, touched = true) {
        const available = new Set(autoResearchSymbols());
        state.autoResearchSelectedSymbols = Array.from(new Set(symbols
            .map((symbol) => String(symbol || '').toUpperCase())
            .filter((symbol) => symbol && available.has(symbol))));
        state.autoResearchSymbolsTouched = touched;
        const select = $('#autoResearchSymbolSelect');
        if (select) {
            [...select.options].forEach((option) => {
                option.selected = state.autoResearchSelectedSymbols.includes(String(option.value || '').toUpperCase());
            });
        }
        $$('[data-auto-research-symbol]').forEach((checkbox) => {
            const checked = state.autoResearchSelectedSymbols.includes(String(checkbox.value || '').toUpperCase());
            checkbox.checked = checked;
            checkbox.closest('.symbol-check')?.classList.toggle('is-checked', checked);
        });
        renderAutoResearchSymbolCount();
    }

    function renderAutoResearchSymbolCount() {
        const target = $('#autoResearchSymbolCount');
        if (!target) return;
        const selected = autoResearchSelectedSymbols().length;
        const total = autoResearchSymbols().length;
        target.textContent = `${number(selected)} / ${number(total)} cặp được chọn`;
    }

    function autoResearchModeLabel(mode) {
        const value = String(mode || '').toLowerCase();
        if (value === 'from_zero') return 'Tự sinh từ số 0';
        if (value === 'optimize_existing' || value === 'one_click_full_symbol') return 'Tối ưu từ bản có sẵn';
        return mode || 'Không rõ';
    }

    function autoResearchFamilyLabel(family) {
        const value = String(family || '');
        const labels = {
            h4_zone_m15_structure_balanced: 'Vùng H4 + M15 cân bằng',
            h4_zone_quality_filter: 'Lọc vùng H4 chất lượng',
            h4_zone_loose_sample_finder: 'Nới điều kiện tìm mẫu',
            h4_zone_strong_rr3: 'Vùng mạnh RR 1:3',
            buy_only_demand: 'Chỉ mua tại vùng canh buy',
            sell_only_supply: 'Chỉ bán tại vùng canh sell',
            zone_extreme_entry: 'Entry tại biên vùng',
            zone_midpoint_entry: 'Entry tại 50% vùng',
            longer_zone_lifecycle: 'Giữ vùng lâu hơn',
            adaptive_fast_sample: 'Tối ưu nhanh: tăng số mẫu',
            adaptive_fast_quality: 'Tối ưu nhanh: tăng chất lượng',
            adaptive_fast_rr: 'Tối ưu nhanh: cải thiện RR',
            adaptive_fast_drawdown: 'Tối ưu nhanh: giảm drawdown',
            adaptive_fast_explore: 'Tối ưu nhanh: thử biến thể',
            adaptive: 'Tự chỉnh theo kết quả',
            optimize_existing: 'Tối ưu bản có sẵn',
        };
        return labels[value] || value || 'Không rõ';
    }

    function startAutoResearchProgressTimer() {
        stopAutoResearchProgressTimer();
        state.autoResearchProgressTimer = setInterval(renderAutoResearchProgress, 1000);
    }

    function stopAutoResearchProgressTimer() {
        if (state.autoResearchProgressTimer) {
            clearInterval(state.autoResearchProgressTimer);
            state.autoResearchProgressTimer = null;
        }
    }

    function requestAutoResearchStop() {
        if (!state.autoResearchRunning) return;
        state.autoResearchCancelRequested = true;
        if (state.autoResearchAbortController) {
            state.autoResearchAbortController.abort();
        }
        showToast('Đang dừng AI Research...');
        renderAutoResearch();
    }

    function autoResearchProgressSnapshot() {
        const batch = state.autoResearchBatch || {};
        const total = Math.max(0, Number(batch.total || 0));
        const completed = Math.min(total, Math.max(0, Number(batch.completed || 0)));
        const now = Date.now();
        const startedAt = Number(batch.startedAt || now);
        const currentStartedAt = Number(batch.currentStartedAt || now);
        const maxSeconds = Math.max(0, Number(batch.maxSeconds || 0));
        const currentElapsedMs = state.autoResearchRunning && completed < total
            ? Math.max(0, now - currentStartedAt)
            : 0;
        const currentLimitMs = maxSeconds > 0 ? maxSeconds * 1000 : 0;
        const currentFraction = total && state.autoResearchRunning && completed < total && currentLimitMs
            ? Math.min(0.95, currentElapsedMs / currentLimitMs)
            : 0;
        const percent = total ? Math.min(100, ((completed + currentFraction) / total) * 100) : 0;
        const elapsedSeconds = Math.max(0, (now - startedAt) / 1000);
        const averageMs = completed > 0
            ? Math.max(1, (now - startedAt) / completed)
            : (currentLimitMs || 0);
        const etaMs = total && averageMs
            ? Math.max(0, averageMs * (total - completed) - currentElapsedMs)
            : 0;
        return {
            total,
            completed,
            percent,
            elapsedSeconds,
            etaSeconds: etaMs / 1000,
            currentElapsedSeconds: currentElapsedMs / 1000,
            maxSeconds,
            index: Math.min(total, Math.max(0, Number(batch.index || completed))),
            symbol: batch.symbol || '',
            methodLabel: batch.methodLabel || '',
        };
    }

    function renderAutoResearchProgress() {
        const box = $('#autoResearchProgress');
        if (!box) return;
        const batch = state.autoResearchBatch;
        if (!state.autoResearchRunning && !batch) {
            box.hidden = true;
            return;
        }
        const snapshot = autoResearchProgressSnapshot();
        box.hidden = false;
        box.classList.toggle('is-cancelling', !!state.autoResearchCancelRequested);
        const title = $('#autoResearchProgressTitle');
        const percent = $('#autoResearchProgressPercent');
        const fill = $('#autoResearchProgressFill');
        const meta = $('#autoResearchProgressMeta');
        if (title) {
            const runningText = snapshot.symbol
                ? `Lượt ${number(snapshot.index)}/${number(snapshot.total)}: ${snapshot.symbol}${snapshot.methodLabel ? ` | ${snapshot.methodLabel}` : ''}`
                : 'Đang chuẩn bị research';
            title.textContent = state.autoResearchCancelRequested ? `Đang dừng - ${runningText}` : runningText;
        }
        if (percent) percent.textContent = `${formatFixed(snapshot.percent, 1)}%`;
        if (fill) fill.style.width = `${Math.max(0, Math.min(100, snapshot.percent))}%`;
        if (meta) {
            const parts = [
                `Hoàn thành ${number(snapshot.completed)}/${number(snapshot.total)} lượt`,
                `Đã chạy ${formatDuration(snapshot.elapsedSeconds)}`,
            ];
            if (snapshot.total) parts.push(`Dự kiến còn ${formatDuration(snapshot.etaSeconds)}`);
            if (snapshot.maxSeconds) parts.push(`Giới hạn lượt này ${formatDuration(snapshot.maxSeconds)}`);
            meta.textContent = parts.join(' | ');
        }
    }

    function autoResearchRunningStatusText() {
        const snapshot = autoResearchProgressSnapshot();
        if (state.autoResearchCancelRequested) {
            return `Đang dừng AI Research. Nếu lượt hiện tại đã vào backend, hệ thống sẽ không chạy lượt tiếp theo. Tiến độ ${formatFixed(snapshot.percent, 1)}%.`;
        }
        if (snapshot.total) {
            return `Đang chạy ${number(snapshot.index)}/${number(snapshot.total)}: ${snapshot.symbol}${snapshot.methodLabel ? ` | ${snapshot.methodLabel}` : ''}. Tiến độ ước tính ${formatFixed(snapshot.percent, 1)}%, còn khoảng ${formatDuration(snapshot.etaSeconds)}. Không đặt lệnh MT5.`;
        }
        return 'Đang chạy full historical backtest và tự sửa rule. Tác vụ này có thể mất vài phút với dữ liệu lớn.';
    }

    async function runAutoResearch() {
        const symbols = autoResearchSelectedSymbols();
        if (!symbols.length) throw new Error('Chưa chọn cặp tiền để chạy research.');
        const mode = $('#autoResearchModeSelect')?.value || 'from_zero';
        const aiProvider = $('#autoResearchAiProviderSelect')?.value || 'internal';
        const methods = autoResearchMethodsForRun(mode);
        if (!methods.length) throw new Error('Chưa chọn phương pháp để chạy research.');
        const maxRounds = Number($('#autoResearchMaxRounds')?.value || (mode === 'from_zero' ? 12 : 6));
        const maxSeconds = Number($('#autoResearchMaxSeconds')?.value || 300);
        const totalRuns = symbols.length * methods.length;
        const startedAt = Date.now();
        state.autoResearchRunning = true;
        state.autoResearchCancelRequested = false;
        state.autoResearchAbortController = null;
        state.autoResearchBatch = {
            index: 0,
            completed: 0,
            total: totalRuns,
            startedAt,
            currentStartedAt: startedAt,
            maxSeconds,
            symbol: '',
            methodLabel: '',
        };
        state.autoResearchError = '';
        state.lastAutoResearchTournament = null;
        startAutoResearchProgressTimer();
        renderAutoResearch();
        try {
            const results = [];
            let runIndex = 0;
            outer:
            for (let index = 0; index < symbols.length; index += 1) {
                const symbol = symbols[index];
                for (let methodIndex = 0; methodIndex < methods.length; methodIndex += 1) {
                    if (state.autoResearchCancelRequested) break outer;
                    const methodProfile = methods[methodIndex];
                    runIndex += 1;
                    const currentStartedAt = Date.now();
                    state.autoResearchBatch = {
                        ...state.autoResearchBatch,
                        index: runIndex,
                        completed: results.length,
                        total: totalRuns,
                        startedAt,
                        currentStartedAt,
                        maxSeconds,
                        symbol,
                        methodProfile,
                        methodLabel: autoResearchMethodLabel(methodProfile),
                    };
                    renderAutoResearch();
                    const controller = new AbortController();
                    state.autoResearchAbortController = controller;
                    const data = await api('auto_research_run', {
                        symbol,
                        mode,
                        aiProvider,
                        methodProfile,
                        strategyVersionId: mode === 'from_zero' ? '' : ($('#autoResearchVersionSelect')?.value || ''),
                        maxRounds,
                        maxSeconds,
                        kpi: {
                            winRateMin: Number($('#autoResearchWinRateMin')?.value || 50),
                            averageRRMin: Number($('#autoResearchAverageRrMin')?.value || 2),
                            profitFactorMin: Number($('#autoResearchProfitFactorMin')?.value || 1.5),
                            maxDrawdownMax: Number($('#autoResearchMaxDrawdownMax')?.value || 10),
                            totalTradesMin: Number($('#autoResearchTotalTradesMin')?.value || 30),
                            netProfitPercentMin: 0,
                        },
                        settings: {
                            initialBalance: 10000,
                            feeR: 0.02,
                            slippagePoints: 2,
                            maxBarsInTrade: 96,
                        },
                    }, { signal: controller.signal });
                    state.autoResearchAbortController = null;
                    if (!data?.research_run) {
                        throw new Error('API chạy xong nhưng không trả kết quả AI Research.');
                    }
                    results.push(data);
                    state.autoResearchBatch = {
                        ...state.autoResearchBatch,
                        completed: results.length,
                        lastDurationMs: Date.now() - currentStartedAt,
                        completedAt: Date.now(),
                    };
                    state.lastAutoResearchRun = data.research_run || state.lastAutoResearchRun;
                    state.autoResearchRuns = data.auto_research_runs || (state.lastAutoResearchRun ? [state.lastAutoResearchRun, ...state.autoResearchRuns] : state.autoResearchRuns);
                    if (data.best_strategy_version?.id) {
                        state.strategyVersions = [
                            data.best_strategy_version,
                            ...(state.strategyVersions || []).filter((version) => version.id !== data.best_strategy_version.id),
                        ];
                        state.selectedStrategyVersionId = data.best_strategy_version.id;
                    }
                    renderAutoResearchProgress();
                    await openAutoResearchRunBestChart(data.research_run, true);
                }
            }

            const bestResult = results
                .filter((item) => item?.research_run?.bestCandidate)
                .sort((a, b) => autoResearchTournamentRankScore(b.research_run) - autoResearchTournamentRankScore(a.research_run))[0] || results[results.length - 1] || null;
            if (bestResult?.research_run) {
                state.lastAutoResearchRun = bestResult.research_run;
            }
            if (bestResult?.best_strategy_version?.id) {
                state.selectedStrategyVersionId = bestResult.best_strategy_version.id;
            }
            if (results.length) {
                state.lastAutoResearchTournament = buildAutoResearchTournament(results, symbols, methods);
            }
            const passed = results.filter((item) => item?.research_run?.status === 'PASSED').length;
            if (state.autoResearchCancelRequested) {
                showToast(`Đã dừng AI Research sau ${number(results.length)}/${number(totalRuns)} lượt.`);
            } else {
                showToast(totalRuns > 1
                    ? `Đã chạy ${totalRuns} lượt research, ${passed} lượt đạt KPI`
                    : (results[0]?.research_run?.status === 'PASSED' ? 'AI Research đã tìm được bản đạt KPI' : 'AI Research đã chạy xong, chưa đạt đủ KPI'));
            }
            if (results.length) {
                await loadAutoResearchRuns();
                if (bestResult?.research_run) {
                    state.lastAutoResearchRun = bestResult.research_run;
                }
                if (state.lastAutoResearchTournament) {
                    renderAutoResearchTournamentBoard(state.lastAutoResearchTournament);
                }
            }
        } catch (error) {
            if (error?.name === 'AbortError') {
                state.autoResearchError = 'Đã dừng AI Research theo yêu cầu. Nếu backend đã nhận lượt hiện tại, app sẽ không chạy lượt tiếp theo.';
            } else {
                state.autoResearchError = error.message || 'AI Research bị lỗi chưa xác định.';
            }
            showToast(state.autoResearchError);
        } finally {
            state.autoResearchRunning = false;
            state.autoResearchCancelRequested = false;
            state.autoResearchAbortController = null;
            state.autoResearchBatch = null;
            stopAutoResearchProgressTimer();
            renderAll();
        }
    }

    async function loadAutoResearchRuns() {
        const data = await api('auto_research_runs', { limit: 20 });
        state.autoResearchRuns = data.auto_research_runs || [];
        state.lastAutoResearchRun = state.autoResearchRuns[0] || null;
        renderAutoResearch();
    }

    function startResearchLabProgressTimer() {
        stopResearchLabProgressTimer();
        renderResearchLabProgress();
        state.researchLabProgressTimer = setInterval(() => renderResearchLabProgress(), 1000);
    }

    function stopResearchLabProgressTimer() {
        if (state.researchLabProgressTimer) {
            clearInterval(state.researchLabProgressTimer);
            state.researchLabProgressTimer = null;
        }
    }

    function prepareResearchLabProgress(session, mode) {
        const cyclesDone = Number((session?.cycles || []).length || 0);
        const maxCycles = Math.max(1, Number(session?.maxCycles || cyclesDone + 1));
        state.researchLabRunMode = mode;
        state.researchLabCurrentSessionId = session?.id || '';
        state.researchLabRunStartedAt = Date.now();
        state.researchLabCycleStartedAt = Date.now();
        state.researchLabCompletedAtStart = cyclesDone;
        state.researchLabTargetCycles = mode === 'one'
            ? Math.min(maxCycles, cyclesDone + 1)
            : maxCycles;
        startResearchLabProgressTimer();
    }

    function researchLabProgressSnapshot(session = selectedResearchLabSession()) {
        const now = Date.now();
        const cyclesDone = Math.max(0, Number((session?.cycles || []).length || 0));
        const maxCycles = Math.max(1, Number(session?.maxCycles || state.researchLabTargetCycles || cyclesDone || 1));
        const completedAtStart = Math.min(cyclesDone, Math.max(0, Number(state.researchLabCompletedAtStart || 0)));
        const targetFromState = Number(state.researchLabTargetCycles || 0);
        const targetCycles = Math.min(maxCycles, Math.max(completedAtStart + 1, targetFromState || maxCycles));
        const totalToRun = Math.max(1, targetCycles - completedAtStart);
        const completedDuringRun = Math.max(0, Math.min(totalToRun, cyclesDone - completedAtStart));
        const runStartedAt = Number(state.researchLabRunStartedAt || now);
        const cycleStartedAt = Number(state.researchLabCycleStartedAt || now);
        const elapsedMs = Math.max(0, now - runStartedAt);
        const currentElapsedMs = state.researchLabRunning && completedDuringRun < totalToRun
            ? Math.max(0, now - cycleStartedAt)
            : 0;
        const estimatedCycleMs = Math.max(1000, Number(state.researchLabLastCycleMs || 0) || 90000);
        const currentFraction = state.researchLabRunning && completedDuringRun < totalToRun
            ? Math.min(0.92, currentElapsedMs / estimatedCycleMs)
            : 0;
        let percent = Math.min(99, ((completedDuringRun + currentFraction) / totalToRun) * 100);
        if (!state.researchLabRunning && state.researchLabRunStartedAt) percent = 100;
        const remainingUnits = Math.max(0, totalToRun - completedDuringRun - currentFraction);
        const nextCycle = Math.min(maxCycles, cyclesDone + (state.researchLabRunning && completedDuringRun < totalToRun ? 1 : 0));
        return {
            symbol: session?.symbol || '',
            maxCycles,
            cyclesDone,
            targetCycles,
            totalToRun,
            completedDuringRun,
            currentCycle: nextCycle || cyclesDone || 1,
            percent,
            elapsedSeconds: elapsedMs / 1000,
            etaSeconds: state.researchLabRunning ? (remainingUnits * estimatedCycleMs) / 1000 : 0,
            modeLabel: state.researchLabRunMode === 'one' ? 'Chạy 1 vòng' : 'Chạy liên tục',
            estimatedCycleSeconds: estimatedCycleMs / 1000,
        };
    }

    function renderResearchLabProgress(session = selectedResearchLabSession()) {
        const box = $('#researchLabProgress');
        if (!box) return;
        const isCurrentSession = !!session && (!state.researchLabCurrentSessionId || session.id === state.researchLabCurrentSessionId);
        const hasProgress = isCurrentSession && (state.researchLabRunning || !!state.researchLabRunStartedAt);
        if (!hasProgress) {
            box.hidden = true;
            return;
        }
        const snapshot = researchLabProgressSnapshot(session);
        box.hidden = false;
        box.classList.toggle('is-cancelling', !!state.researchLabStopRequested);
        const title = $('#researchLabProgressTitle');
        const percent = $('#researchLabProgressPercent');
        const fill = $('#researchLabProgressFill');
        const meta = $('#researchLabProgressMeta');
        const status = $('#researchLabStatus');
        const titlePrefix = state.researchLabRunning
            ? (state.researchLabStopRequested ? 'Đang dừng sau vòng này' : snapshot.modeLabel)
            : 'Lượt chạy gần nhất đã xong';
        if (title) title.textContent = `${titlePrefix}: ${snapshot.symbol || 'AI Lab'} - vòng ${number(snapshot.currentCycle)}/${number(snapshot.maxCycles)}`;
        if (percent) percent.textContent = `${formatFixed(snapshot.percent, 1)}%`;
        if (fill) fill.style.width = `${Math.max(0, Math.min(100, snapshot.percent))}%`;
        if (meta) {
            const parts = [
                `Lượt này ${number(snapshot.completedDuringRun)}/${number(snapshot.totalToRun)} vòng`,
                `Tổng phiên ${number(snapshot.cyclesDone)}/${number(snapshot.maxCycles)} vòng`,
                `Đã chạy ${formatDuration(snapshot.elapsedSeconds)}`,
            ];
            if (state.researchLabRunning) parts.push(`Ước tính còn ${formatDuration(snapshot.etaSeconds)}`);
            if (state.researchLabRunning && !state.researchLabLastCycleMs) parts.push('ETA sẽ chính xác hơn sau vòng đầu');
            parts.push('% là ước tính vì backend trả kết quả theo từng vòng');
            meta.textContent = parts.join(' | ');
        }
        if (status && state.researchLabRunning) status.textContent = researchLabRunningStatusText(session);
    }

    function researchLabRunningStatusText(session = selectedResearchLabSession()) {
        const snapshot = researchLabProgressSnapshot(session);
        if (state.researchLabStopRequested) {
            return `Đang dừng sau vòng hiện tại. Vòng ${number(snapshot.currentCycle)}/${number(snapshot.maxCycles)}, tiến độ ước tính ${formatFixed(snapshot.percent, 1)}%.`;
        }
        return `${snapshot.modeLabel}: vòng ${number(snapshot.currentCycle)}/${number(snapshot.maxCycles)} của ${snapshot.symbol || 'AI Lab'}. Tiến độ ước tính ${formatFixed(snapshot.percent, 1)}%, còn khoảng ${formatDuration(snapshot.etaSeconds)}. Không đặt lệnh MT5.`;
    }

    function renderResearchLab() {
        const form = $('#researchLabForm');
        if (!form) return;
        const symbols = autoResearchSymbols();
        const symbolSelect = $('#researchLabSymbolSelect');
        const currentSymbol = symbolSelect?.value || symbols[0] || '';
        if (symbolSelect) {
            symbolSelect.innerHTML = symbols.length
                ? symbols.map((symbol) => `<option value="${escapeAttr(symbol)}">${escapeHtml(symbol)}</option>`).join('')
                : '<option value="">Chưa có cặp đủ M15/H4</option>';
            symbolSelect.value = symbols.includes(currentSymbol) ? currentSymbol : (symbols[0] || '');
        }

        const sessions = state.researchLabSessions || [];
        if (!state.selectedResearchLabId && sessions[0]) state.selectedResearchLabId = sessions[0].id;
        const session = selectedResearchLabSession();
        const runningSession = state.researchLabCurrentSessionId
            ? (sessions.find((item) => item.id === state.researchLabCurrentSessionId) || session)
            : session;
        $('#researchLabSessionCount').textContent = `${number(sessions.length)} phiên`;
        const status = $('#researchLabStatus');
        if (status) status.textContent = state.researchLabRunning
            ? researchLabRunningStatusText(runningSession)
            : (session?.statusText || 'Chưa có phiên AI Lab.');
        $('#runResearchLabOneBtn').disabled = state.researchLabRunning || !session;
        $('#runResearchLabLoopBtn').disabled = state.researchLabRunning || !session;
        $('#createResearchLabBtn').disabled = state.researchLabRunning || !symbols.length;
        const stopBtn = $('#stopResearchLabBtn');
        if (stopBtn) stopBtn.hidden = !state.researchLabRunning || !session;
        renderResearchLabProgress(runningSession || session);
        renderResearchLabSessions();
        renderResearchLabDetail(session);
    }

    function selectedResearchLabSession() {
        return (state.researchLabSessions || []).find((item) => item.id === state.selectedResearchLabId) || state.researchLabSessions?.[0] || null;
    }

    function renderResearchLabSessions() {
        const target = $('#researchLabSessions');
        if (!target) return;
        const sessions = state.researchLabSessions || [];
        if (!sessions.length) {
            target.innerHTML = '<div class="notice">Chưa có phiên 2 AI nào. Chọn cặp tiền rồi bấm Tạo phiên 2 AI mới.</div>';
            return;
        }
        target.innerHTML = sessions.map((session) => {
            const best = session.bestCycle?.backtestAgent?.summary || {};
            const active = session.id === state.selectedResearchLabId ? ' is-active' : '';
            return `<button class="profile-item research-lab-session-item${active}" type="button" data-research-lab-session="${escapeAttr(session.id)}">
                <strong>${escapeHtml(session.symbol || '')} | ${escapeHtml(researchLabStatusLabel(session.status))} | ${number((session.cycles || []).length)}/${number(session.maxCycles || 0)} vòng</strong>
                <span>${escapeHtml(session.methodProfileLabel || '')}</span>
                <span>Best: ${number(best.totalTrades || 0)} lệnh | WR ${formatFixed(best.winRate || 0, 2)}% | RR ${formatFixed(best.averageRR || 0, 2)} | PF ${formatFixed(best.profitFactor || 0, 2)}</span>
            </button>`;
        }).join('');
        $$('.research-lab-session-item').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedResearchLabId = button.dataset.researchLabSession || '';
                renderResearchLab();
            });
        });
    }

    function renderResearchLabDetail(session) {
        const target = $('#researchLabDetail');
        if (!target) return;
        if (!session) {
            target.innerHTML = `
                <div class="research-lab-empty-state">
                    <div class="research-lab-empty-agents">
                        <div class="research-lab-agent-card is-researcher">
                            <span class="research-lab-agent-badge">AI số 1</span>
                            <strong>Nhà nghiên cứu chiến lược</strong>
                            <span>Chưa được tạo phiên. Khi tạo phiên, AI này sẽ chọn hướng thử và sửa rule sau mỗi lần backtest.</span>
                        </div>
                        <div class="research-lab-agent-card is-backtester">
                            <span class="research-lab-agent-badge">AI số 2</span>
                            <strong>Máy backtest & kiểm chứng</strong>
                            <span>Chưa có việc để chạy. Khi có rule từ AI số 1, AI này sẽ backtest bằng dữ liệu nến trong app.</span>
                        </div>
                    </div>
                    <div class="notice">Bước tiếp theo: chọn cặp tiền, giữ nguồn AI số 1 là OpenAI API nếu muốn dùng key đã lưu, rồi bấm Tạo phiên 2 AI mới.</div>
                </div>
            `;
            return;
        }
        const best = session.bestCycle?.backtestAgent?.summary || {};
        const cycles = session.cycles || [];
        target.innerHTML = `
            <div class="research-lab-summary-grid">
                ${researchLabMetric('Trạng thái', researchLabStatusLabel(session.status))}
                ${researchLabMetric('Vòng đã chạy', `${number(cycles.length)} / ${number(session.maxCycles || 0)}`)}
                ${researchLabMetric('Nguồn AI số 1', session.aiProviderLabel || (session.aiProvider === 'openai_chat' ? 'OpenAI API' : 'AI nội bộ'))}
                ${researchLabMetric('Best score', formatFixed(session.bestCycle?.score || 0, 2))}
                ${researchLabMetric('Best result', `${number(best.totalTrades || 0)} lệnh | WR ${formatFixed(best.winRate || 0, 2)}% | PF ${formatFixed(best.profitFactor || 0, 2)}`)}
            </div>
            <div class="notice research-lab-status-note">${escapeHtml(session.statusText || '')}</div>
            <div class="research-lab-cycle-list">
                ${cycles.length ? cycles.slice().reverse().map((cycle) => researchLabCycleHtml(session, cycle)).join('') : `<div class="research-lab-empty-state"><div class="research-lab-empty-agents"><div class="research-lab-agent-card is-researcher"><span class="research-lab-agent-badge">AI số 1</span><strong>Đang chờ lệnh chạy</strong><span>Sẽ đề xuất/sửa rule cho vòng đầu tiên.</span></div><div class="research-lab-agent-card is-backtester"><span class="research-lab-agent-badge">AI số 2</span><strong>Đang chờ rule</strong><span>Sẽ backtest ngay sau khi AI số 1 gửi bộ luật.</span></div></div><div class="notice">Phiên 2 AI đã tạo. Bấm Chạy 1 vòng để xem từng AI làm việc, hoặc Chạy liên tục để hai AI tự lặp.</div></div>`}
            </div>
        `;
        $$('[data-research-lab-open-cycle]').forEach((button) => {
            button.addEventListener('click', () => openResearchLabCycleChart(button.dataset.researchLabSession, Number(button.dataset.researchLabOpenCycle || 0), button.dataset.researchLabTimeframe || 'M15').catch((e) => showToast(e.message)));
        });
    }

    function researchLabCycleHtml(session, cycle) {
        const summary = cycle.backtestAgent?.summary || {};
        const diagnostics = cycle.backtestAgent?.diagnostics || {};
        const changes = cycle.researchAgent?.changePlan || [];
        const fail = (cycle.failReasons || []).join(' ') || 'Không có';
        return `<section class="research-lab-cycle">
            <div class="research-lab-cycle-head">
                <div>
                    <strong>Vòng ${number(cycle.cycle)} | ${escapeHtml(cycle.researchAgent?.methodFamily || '')} | ${escapeHtml(researchLabStatusLabel(cycle.status))}</strong>
                    <span>Score ${formatFixed(cycle.score || 0, 2)} | ${number(summary.totalTrades || 0)} lệnh | WR ${formatFixed(summary.winRate || 0, 2)}% | RR ${formatFixed(summary.averageRR || 0, 2)} | PF ${formatFixed(summary.profitFactor || 0, 2)} | DD ${formatFixed(summary.maxDrawdownPercent || 0, 2)}%</span>
                </div>
                <div class="topbar-actions">
                    <button class="button secondary" type="button" data-research-lab-session="${escapeAttr(session.id)}" data-research-lab-open-cycle="${escapeAttr(cycle.cycle)}" data-research-lab-timeframe="M15">M15</button>
                    <button class="button secondary" type="button" data-research-lab-session="${escapeAttr(session.id)}" data-research-lab-open-cycle="${escapeAttr(cycle.cycle)}" data-research-lab-timeframe="H4">H4</button>
                </div>
            </div>
            <div class="research-lab-agent-grid">
                <div class="research-lab-agent"><strong>AI số 1 - Nhà nghiên cứu</strong><span>${escapeHtml(cycle.researchAgent?.hypothesis || '')}</span><small>${escapeHtml(cycle.researchAgent?.provider === 'openai_chat' ? 'OpenAI API' : 'AI nội bộ')} | ${escapeHtml(cycle.researchAgent?.providerReason || '')}</small><small>${changes.slice(0, 4).map((c) => `${escapeHtml(c.field || '')}: ${escapeHtml(String(c.newValue ?? ''))}`).join(' | ') || 'Không đổi rule.'}</small></div>
                <div class="research-lab-agent"><strong>AI số 2 - Máy backtest</strong><span>${number(summary.totalTrades || 0)} lệnh, ${number(summary.winTrades || 0)} thắng, ${number(summary.lossTrades || 0)} thua.</span><small>Xét ${number(diagnostics.setupsConsidered || 0)} setup, bỏ ${number(diagnostics.setupsRejected || 0)}. Lý do bỏ nhiều nhất: ${escapeHtml(diagnostics.topSkippedReason || 'Không có')}</small></div>
                <div class="research-lab-agent"><strong>AI số 1 - Nhận kết quả</strong><span>${escapeHtml(cycle.reviewAgent?.verdict || '')}</span><small>${escapeHtml(cycle.reviewAgent?.nextInstruction || fail)}</small></div>
            </div>
            ${researchLabTradePreviewHtml(session, cycle)}
        </section>`;
    }

    function researchLabTradePreviewHtml(session, cycle) {
        const trades = cycle.backtestAgent?.trades || [];
        if (!trades.length) return '<div class="notice">Vòng này chưa phát sinh lệnh. Xem diagnostics để biết điều kiện nào đang chặn.</div>';
        return `<details class="workflow-details research-lab-trades"><summary><span>Bảng lệnh vòng ${number(cycle.cycle)}</span><small>${number(trades.length)} lệnh, bấm M15/H4 ở trên để mở chart.</small></summary><div class="research-trade-table">${trades.slice(0, 80).map((trade, index) => researchLabTradeRow(trade, index)).join('')}</div></details>`;
    }

    function researchLabTradeRow(trade, index) {
        const netR = Number(trade.netR ?? trade.rrResult ?? 0);
        const direction = String(trade.direction || '').toUpperCase();
        return `<div class="backtest-trade-row research-trade-row">
            <span class="trade-side ${direction === 'BUY' ? 'long' : 'short'}">${escapeHtml(displayDirection(direction))}</span>
            <div class="trade-row-main">
                <strong>#${number(index + 1)} | ${escapeHtml(translateDisplayText(trade.result || ''))} | ${escapeHtml(formatChartTime(trade.entryTime || ''))}</strong>
                <span>${escapeHtml(formatPrice(trade.entryPrice, trade.symbol))} -> ${escapeHtml(formatPrice(trade.exitPrice, trade.symbol))} | ${escapeHtml(trade.priceActionPattern ? `PA: ${trade.priceActionPattern} | ` : '')}${escapeHtml(translateDisplayText(trade.reasonEnter || ''))}</span>
            </div>
            <span class="trade-r ${netR >= 0 ? 'win' : 'loss'}">${formatSigned(netR, 2)}R</span>
        </div>`;
    }

    function researchLabMetric(label, value) {
        return `<div class="strategy-version-current-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
    }

    function researchLabStatusLabel(status) {
        const map = { ACTIVE: 'Đang nghiên cứu', PASSED: 'Đạt KPI', FAILED: 'Chưa đạt', NEEDS_WORK: 'Cần sửa tiếp' };
        return map[String(status || '').toUpperCase()] || String(status || 'Không rõ');
    }

    function readResearchLabPayload() {
        return {
            symbol: $('#researchLabSymbolSelect')?.value || '',
            methodProfile: $('#researchLabMethodSelect')?.value || 'h4_trend_zone_m15_pa',
            aiProvider: $('#researchLabAiProviderSelect')?.value || 'openai_chat',
            maxCycles: intNum($('#researchLabMaxCycles')?.value || 20),
            kpi: {
                winRateMin: num($('#researchLabWinRateMin')?.value || 50),
                averageRRMin: num($('#researchLabAverageRrMin')?.value || 2),
                profitFactorMin: num($('#researchLabProfitFactorMin')?.value || 1.5),
                maxDrawdownMax: num($('#researchLabMaxDrawdownMax')?.value || 10),
                totalTradesMin: intNum($('#researchLabTotalTradesMin')?.value || 50),
                netProfitPercentMin: 0,
            },
            settings: {
                initialBalance: 10000,
                feeR: 0.02,
                slippagePoints: 2,
                maxBarsInTrade: intNum($('#researchLabMaxBarsInTrade')?.value || 96),
            },
        };
    }

    function applyResearchLabResponse(data) {
        state.researchLabSessions = data.research_lab_sessions || state.researchLabSessions || [];
        if (data.research_lab_session?.id) state.selectedResearchLabId = data.research_lab_session.id;
        if (data.strategy_versions) state.strategyVersions = data.strategy_versions;
        if (data.best_strategy_version?.id) state.selectedStrategyVersionId = data.best_strategy_version.id;
    }

    async function createResearchLabSession() {
        const payload = readResearchLabPayload();
        if (!payload.symbol) throw new Error('Chưa có cặp tiền đủ dữ liệu M15/H4 để tạo phiên.');
        const data = await api('research_lab_create', payload);
        applyResearchLabResponse(data);
        showToast('Đã tạo phiên AI Lab mới');
        renderResearchLab();
    }

    async function loadResearchLabSessions() {
        const data = await api('research_lab_sessions', { limit: 20 });
        state.researchLabSessions = data.research_lab_sessions || [];
        state.selectedResearchLabId = state.selectedResearchLabId || state.researchLabSessions[0]?.id || '';
        renderResearchLab();
    }

    async function runResearchLabCycleRequest(sessionId) {
        const data = await api('research_lab_run_cycle', { sessionId });
        applyResearchLabResponse(data);
        renderResearchLab();
        return data.research_lab_session || selectedResearchLabSession();
    }

    async function ensureResearchLabSession() {
        let session = selectedResearchLabSession();
        if (session) return session;
        await createResearchLabSession();
        session = selectedResearchLabSession();
        if (!session) throw new Error('Chưa có phiên AI Lab để chạy.');
        return session;
    }

    async function runResearchLabOne() {
        const session = await ensureResearchLabSession();
        state.researchLabRunning = true;
        state.researchLabStopRequested = false;
        state.researchLabError = '';
        prepareResearchLabProgress(session, 'one');
        renderResearchLab();
        try {
            state.researchLabCycleStartedAt = Date.now();
            const cycleStartedAt = state.researchLabCycleStartedAt;
            const updatedSession = await runResearchLabCycleRequest(session.id);
            state.researchLabLastCycleMs = Math.max(1000, Date.now() - cycleStartedAt);
            renderResearchLabProgress(updatedSession);
            showToast('AI Lab đã chạy xong 1 vòng');
        } catch (error) {
            state.researchLabError = error.message;
            showToast(error.message);
        } finally {
            state.researchLabRunning = false;
            stopResearchLabProgressTimer();
            renderResearchLab();
        }
    }

    async function runResearchLabLoop() {
        let session = await ensureResearchLabSession();
        state.researchLabRunning = true;
        state.researchLabStopRequested = false;
        state.researchLabError = '';
        prepareResearchLabProgress(session, 'loop');
        renderResearchLab();
        try {
            while (session && session.status === 'ACTIVE' && !state.researchLabStopRequested) {
                state.researchLabCycleStartedAt = Date.now();
                const cycleStartedAt = state.researchLabCycleStartedAt;
                renderResearchLabProgress(session);
                session = await runResearchLabCycleRequest(session.id);
                state.researchLabLastCycleMs = Math.max(1000, Date.now() - cycleStartedAt);
                renderResearchLabProgress(session);
                if (!session || session.status !== 'ACTIVE') break;
                await new Promise((resolve) => setTimeout(resolve, 120));
            }
            showToast(state.researchLabStopRequested ? 'Đã dừng AI Lab sau vòng hiện tại' : 'AI Lab đã kết thúc phiên chạy');
        } catch (error) {
            state.researchLabError = error.message;
            showToast(error.message);
        } finally {
            state.researchLabRunning = false;
            state.researchLabStopRequested = false;
            stopResearchLabProgressTimer();
            renderResearchLab();
        }
    }

    function requestResearchLabStop() {
        state.researchLabStopRequested = true;
        renderResearchLab();
        showToast('AI Lab sẽ dừng sau vòng hiện tại');
    }

    async function openResearchLabCycleChart(sessionId, cycleNumber, timeframe = 'M15') {
        const session = (state.researchLabSessions || []).find((item) => item.id === sessionId) || selectedResearchLabSession();
        const cycle = (session?.cycles || []).find((item) => Number(item.cycle) === Number(cycleNumber));
        if (!session || !cycle) {
            showToast('Không tìm thấy vòng AI Lab để mở chart.');
            return;
        }
        const summary = cycle.backtestAgent?.summary || {};
        const symbol = String(summary.symbol || session.symbol || '').toUpperCase();
        const chartTimeframe = String(timeframe || summary.timeframe || 'M15').toUpperCase();
        const file = findMarketFile(symbol, chartTimeframe) || findMarketFile(symbol, 'M15') || findMarketFile(symbol, 'H4');
        if (!file) {
            showToast(`${symbol} chưa có dữ liệu ${chartTimeframe}. Vào Dữ liệu & MT5 để tải trước.`);
            return;
        }
        const chartRun = researchLabCycleToBacktestRun(session, cycle);
        state.backtestRuns = [
            chartRun,
            ...(state.backtestRuns || []).filter((item) => (item.backtestRunId || item.id || '') !== chartRun.backtestRunId),
        ];
        state.chartView.timeframe = file.timeframe;
        state.chartView.activeRunId = chartRun.backtestRunId;
        state.chartView.tradeFilter = 'all';
        state.chartView.overlays.markers = true;
        state.chartView.overlays.slTp = true;
        state.chartView.overlays.positionBands = true;
        state.chartView.overlays.zones = true;
        activateTab('mt5');
        await loadMarketData(file.file);
        state.chartView.activeRunId = chartRun.backtestRunId;
        const activeRun = activeBacktestRun(getChartAnalytics(state.activeMarketData));
        focusBacktestRunTradeRange(activeRun);
        showToast(`Đã mở vòng ${number(cycle.cycle)} của AI Lab trên chart ${file.timeframe}`);
    }

    function researchLabCycleToBacktestRun(session, cycle) {
        const summary = { ...(cycle.backtestAgent?.summary || {}) };
        if (cycle.backtestAgent?.diagnostics && !summary.diagnostics) summary.diagnostics = cycle.backtestAgent.diagnostics;
        const backtestRunId = summary.backtestRunId || `${session.id}_cycle_${cycle.cycle}`;
        return {
            id: backtestRunId,
            backtestRunId,
            strategyVersionId: session.bestStrategyVersionId || session.id,
            symbol: session.symbol || summary.symbol || '',
            timeframe: summary.timeframe || 'M15',
            contextTimeframe: summary.contextTimeframe || 'H4',
            rangeType: summary.dataSplit || 'custom',
            dataSplit: summary.dataSplit || 'custom',
            createdAt: cycle.createdAt || session.updatedAt || '',
            summary,
            trades: cycle.backtestAgent?.trades || [],
            debug: null,
            source: 'research_lab',
        };
    }

    /* RESEARCH_LAB_FUNCTIONS */

    function renderOpenAiSettings() {
        const summary = $('#openAiSettingsSummary');
        if (!summary) return;
        const settings = state.openAiSettings || {};
        const status = settings.status || {};
        const budget = settings.budget || {};
        const hasKey = settings.api_key_configured === true;
        const readyText = settings.ready ? 'Sẵn sàng' : 'Chưa sẵn sàng';
        const budgetText = `${formatUsd(budget.spent_usd || 0)} / ${formatUsd(budget.budget_usd ?? settings.daily_budget_usd ?? 1)}`;
        const price = budget.price || {};
        const maxCallsText = budget.max_calls > 0 ? `${number(budget.calls || 0)} / ${number(budget.max_calls)}` : `${number(budget.calls || 0)} / Không giới hạn`;
        summary.innerHTML = [
            memoryRow('Trạng thái', readyText),
            memoryRow('API key', settings.api_key_label || (settings.api_key_configured ? 'Đã cấu hình' : 'Chưa lưu key')),
            memoryRow('Provider', status.enabled_status || 'disabled'),
            memoryRow('Model', settings.model || 'gpt-6-luna'),
            memoryRow('Ngân sách hôm nay', budgetText),
            memoryRow('Còn lại', formatUsd(budget.remaining_usd || 0)),
            memoryRow('Số lần gọi hôm nay', maxCallsText),
            memoryRow('Giá đang tính', `${formatUsd(price.input_per_1m || 0)} in / ${formatUsd(price.output_per_1m || 0)} out mỗi 1M token`),
            memoryRow('Tình trạng ngân sách', budget.allowed === false ? (budget.block_reason || 'Đang bị chặn') : 'Còn dùng được'),
        ].join('');

        const keyCard = $('#openAiKeyCard');
        if (keyCard) {
            keyCard.className = `openai-key-card ${hasKey ? 'is-saved' : 'is-empty'}`;
            keyCard.innerHTML = hasKey
                ? `<strong>Đang dùng key đã lưu</strong>
                   <span>Mã key: <code>${escapeHtml(settings.api_key_label || 'Đã lưu key')}</code></span>
                   <span>Key được lưu trong <code>.env.local</code>. App chỉ hiện 4 ký tự cuối để tránh lộ key.</span>`
                : `<strong>Chưa có API key trong app</strong>
                   <span>Dán key mới vào ô bên dưới rồi bấm <strong>Lưu cấu hình</strong>. Không gửi key vào chat.</span>`;
        }

        const hasAdminKey = settings.admin_api_key_configured === true;
        const adminKeyCard = $('#openAiAdminKeyCard');
        if (adminKeyCard) {
            adminKeyCard.className = `openai-key-card ${hasAdminKey ? 'is-saved' : 'is-empty'}`;
            adminKeyCard.innerHTML = hasAdminKey
                ? `<strong>Đã lưu OpenAI Admin API key</strong>
                   <span>Mã key: <code>${escapeHtml(settings.admin_api_key_label || 'Đã lưu admin key')}</code></span>
                   <span>Admin key chỉ dùng để đọc chi phí chính thức từ OpenAI Costs API.</span>`
                : `<strong>Chưa có Admin API key</strong>
                   <span>Dán admin key vào ô bên dưới để đồng bộ chi phí chính thức. Key này khác key gọi model.</span>`;
        }

        renderOpenAiOfficialCosts(settings);

        const enabledInput = $('#openAiEnabledInput');
        if (enabledInput) enabledInput.checked = settings.enabled !== false;
        const keyInput = $('#openAiApiKeyInput');
        if (keyInput && document.activeElement !== keyInput) {
            keyInput.placeholder = hasKey ? 'Dán key mới nếu muốn thay key đã lưu' : 'sk-...';
        }
        const keyHint = $('#openAiApiKeyHint');
        if (keyHint) {
            keyHint.textContent = hasKey
                ? 'Để trống nếu muốn giữ key hiện tại. Dán key mới vào đây rồi bấm Lưu cấu hình để thay key.'
                : 'Dán API key vào đây rồi bấm Lưu cấu hình để thêm key vào app.';
        }
        const modelInput = $('#openAiModelInput');
        if (modelInput && document.activeElement !== modelInput) modelInput.value = settings.model || 'gpt-6-luna';
        const budgetInput = $('#openAiDailyBudgetInput');
        if (budgetInput && document.activeElement !== budgetInput) budgetInput.value = String(settings.daily_budget_usd ?? budget.budget_usd ?? 1);
        const tokenInput = $('#openAiMaxTokensInput');
        if (tokenInput && document.activeElement !== tokenInput) tokenInput.value = String(settings.max_completion_tokens || 1200);
        const maxCallsInput = $('#openAiMaxCallsInput');
        if (maxCallsInput && document.activeElement !== maxCallsInput) maxCallsInput.value = String(settings.max_calls_per_day ?? budget.max_calls ?? 100);
        const adminKeyInput = $('#openAiAdminKeyInput');
        if (adminKeyInput && document.activeElement !== adminKeyInput) {
            adminKeyInput.placeholder = hasAdminKey ? 'Dán admin key mới nếu muốn thay key đã lưu' : 'sk-admin-...';
        }
        const adminKeyHint = $('#openAiAdminKeyHint');
        if (adminKeyHint) {
            adminKeyHint.textContent = hasAdminKey
                ? 'Để trống nếu muốn giữ admin key hiện tại. Dán key mới rồi bấm Lưu admin key để thay.'
                : 'Admin key tạo ở OpenAI Platform → Organization → Admin keys. Không dùng key sk-proj thường.';
        }

        const saveButton = $('#saveOpenAiSettingsBtn');
        if (saveButton) {
            saveButton.disabled = state.openAiSettingsSaving || state.openAiSettingsDeleting;
            saveButton.textContent = state.openAiSettingsSaving ? 'Đang lưu...' : 'Lưu cấu hình';
        }
        const editButton = $('#editOpenAiKeyBtn');
        if (editButton) {
            editButton.disabled = state.openAiSettingsSaving || state.openAiSettingsDeleting;
            editButton.textContent = hasKey ? 'Thay key' : 'Thêm key';
        }
        const deleteButton = $('#deleteOpenAiKeyBtn');
        if (deleteButton) {
            deleteButton.disabled = !hasKey || state.openAiSettingsSaving || state.openAiSettingsDeleting;
            deleteButton.textContent = state.openAiSettingsDeleting ? 'Đang xóa...' : 'Xóa key';
        }
        const adminSaveButton = $('#saveOpenAiAdminKeyBtn');
        if (adminSaveButton) {
            adminSaveButton.disabled = state.openAiAdminKeySaving || state.openAiAdminKeyDeleting;
            adminSaveButton.textContent = state.openAiAdminKeySaving ? 'Đang lưu...' : 'Lưu admin key';
        }
        const adminEditButton = $('#editOpenAiAdminKeyBtn');
        if (adminEditButton) {
            adminEditButton.disabled = state.openAiAdminKeySaving || state.openAiAdminKeyDeleting;
            adminEditButton.textContent = hasAdminKey ? 'Thay admin key' : 'Thêm admin key';
        }
        const adminDeleteButton = $('#deleteOpenAiAdminKeyBtn');
        if (adminDeleteButton) {
            adminDeleteButton.disabled = !hasAdminKey || state.openAiAdminKeySaving || state.openAiAdminKeyDeleting;
            adminDeleteButton.textContent = state.openAiAdminKeyDeleting ? 'Đang xóa...' : 'Xóa admin key';
        }
        const syncButton = $('#syncOpenAiOfficialCostsBtn');
        if (syncButton) {
            syncButton.disabled = !hasAdminKey || state.openAiOfficialCostSyncing;
            syncButton.textContent = state.openAiOfficialCostSyncing ? 'Đang đồng bộ...' : 'Đồng bộ chi phí';
        }
        const testButton = $('#testOpenAiSettingsBtn');
        if (testButton) {
            testButton.disabled = state.openAiSettingsTesting || !settings.ready || budget.allowed === false;
            testButton.textContent = state.openAiSettingsTesting ? 'Đang test OpenAI...' : 'Test OpenAI';
        }

        const result = $('#openAiTestResult');
        if (result) {
            if (state.openAiSettingsError) {
                result.innerHTML = `<div class="notice">${escapeHtml(state.openAiSettingsError)}</div>`;
            } else if (state.openAiSettingsTesting) {
                result.innerHTML = '<div class="notice">Đang gọi OpenAI để test cấu hình...</div>';
            } else if (state.openAiTestResult) {
                result.innerHTML = promptGatewayResultHtml(state.openAiTestResult);
            } else {
                result.innerHTML = '<div class="notice">Lưu cấu hình xong rồi bấm Test OpenAI để kiểm tra key/model. Test sẽ dùng một lượng token nhỏ.</div>';
            }
        }
    }

    function renderOpenAiOfficialCosts(settings) {
        const box = $('#openAiOfficialCostSummary');
        if (!box) return;
        const official = settings.official_costs || {};
        const budget = settings.budget || {};
        if (state.openAiOfficialCostSyncing) {
            box.innerHTML = memoryRow('Trạng thái', 'Đang lấy số chính thức từ OpenAI Costs API...');
            return;
        }
        if (state.openAiOfficialCostError) {
            box.innerHTML = [
                memoryRow('OpenAI chính thức', 'Đồng bộ lỗi'),
                memoryRow('Lỗi', state.openAiOfficialCostError),
                memoryRow('App ước tính hôm nay', formatUsd(budget.spent_usd || 0)),
            ].join('');
            return;
        }
        if (!official.ok) {
            box.innerHTML = [
                memoryRow('OpenAI chính thức', official.message || 'Chưa đồng bộ'),
                memoryRow('App ước tính hôm nay', formatUsd(budget.spent_usd || 0)),
                memoryRow('Ghi chú', 'Cần Admin API key để lấy số chính thức.'),
            ].join('');
            return;
        }

        const lines = Array.isArray(official.line_items) && official.line_items.length
            ? official.line_items
                .slice(0, 5)
                .map((item) => `<span>${escapeHtml(item.line_item || 'Không phân loại')}: ${formatUsd(item.amount || 0)}</span>`)
                .join('')
            : '<span>Không có line item chi tiết.</span>';

        box.innerHTML = [
            memoryRow('OpenAI chính thức hôm nay', formatUsd(official.official_total_usd || 0)),
            memoryRow('App ước tính hôm nay', formatUsd(official.local_estimated_spent_usd ?? budget.spent_usd ?? 0)),
            memoryRow('Chênh lệch', formatUsd(official.difference_usd || 0)),
            memoryRow('Đồng bộ lúc', formatDateTime(official.synced_at || '')),
            memoryRow('Kỳ tính', 'Ngày UTC'),
            `<div class="openai-cost-lines">${lines}</div>`,
        ].join('');
    }

    function formatUsd(value) {
        const amount = Number(value || 0);
        const decimals = amount >= 1 ? 2 : amount >= 0.01 ? 4 : 6;
        return `$${amount.toFixed(decimals)}`;
    }

    function autoSyncOpenAiOfficialCosts() {
        if (!state.openAiSettings?.admin_api_key_configured || state.openAiOfficialCostSyncing) {
            return;
        }
        syncOpenAiOfficialCosts({ silent: true }).catch(() => {
            renderOpenAiSettings();
        });
    }

    async function loadOpenAiSettings() {
        state.openAiSettingsLoading = true;
        state.openAiSettingsError = '';
        renderOpenAiSettings();
        try {
            const data = await api('openai_local_settings_status');
            state.openAiSettings = data;
        } catch (error) {
            state.openAiSettingsError = error.message;
            throw error;
        } finally {
            state.openAiSettingsLoading = false;
            renderOpenAiSettings();
            renderAutoResearch();
        }
    }

    async function saveOpenAiSettings(event) {
        event.preventDefault();
        const payload = {
            enabled: $('#openAiEnabledInput')?.checked !== false,
            apiKey: $('#openAiApiKeyInput')?.value.trim() || '',
            model: $('#openAiModelInput')?.value.trim() || 'gpt-6-luna',
            dailyBudgetUsd: Number($('#openAiDailyBudgetInput')?.value || 1),
            maxCompletionTokens: Number($('#openAiMaxTokensInput')?.value || 1200),
            maxCallsPerDay: Number($('#openAiMaxCallsInput')?.value || 0),
        };
        state.openAiSettingsSaving = true;
        state.openAiSettingsError = '';
        renderOpenAiSettings();
        try {
            const data = await api('openai_local_settings_save', payload, { timeoutMs: 20000 });
            if (payload.apiKey && data.api_key_configured !== true) {
                throw new Error('Máy chủ đã phản hồi nhưng chưa xác nhận lưu được API key. Kiểm tra quyền ghi cấu hình trên hosting.');
            }
            state.openAiSettings = data;
            const keyInput = $('#openAiApiKeyInput');
            if (keyInput) keyInput.value = '';
            showToast('Đã lưu cấu hình OpenAI local');
        } catch (error) {
            state.openAiSettingsError = error.message;
            throw error;
        } finally {
            state.openAiSettingsSaving = false;
            renderOpenAiSettings();
            renderAutoResearch();
        }
    }

    function focusOpenAiKeyInput() {
        const input = $('#openAiApiKeyInput');
        if (!input) return;
        input.focus();
        input.select();
        showToast(state.openAiSettings?.api_key_configured ? 'Dán key mới rồi bấm Lưu cấu hình để thay key' : 'Dán API key rồi bấm Lưu cấu hình để thêm key');
    }

    function focusOpenAiAdminKeyInput() {
        const input = $('#openAiAdminKeyInput');
        if (!input) return;
        input.focus();
        input.select();
        showToast(state.openAiSettings?.admin_api_key_configured ? 'Dán admin key mới rồi bấm Lưu admin key để thay' : 'Dán OpenAI Admin API key rồi bấm Lưu admin key');
    }

    async function deleteOpenAiApiKey() {
        if (!state.openAiSettings?.api_key_configured) {
            showToast('Chưa có API key để xóa');
            return;
        }
        if (!window.confirm('Xóa API key khỏi app local? Key gốc trên OpenAI Platform không bị revoke.')) {
            return;
        }

        state.openAiSettingsDeleting = true;
        state.openAiSettingsError = '';
        state.openAiTestResult = null;
        renderOpenAiSettings();
        try {
            const data = await api('openai_local_settings_delete_key', {});
            state.openAiSettings = data;
            const keyInput = $('#openAiApiKeyInput');
            if (keyInput) keyInput.value = '';
            showToast('Đã xóa API key khỏi app local');
        } catch (error) {
            state.openAiSettingsError = error.message;
            throw error;
        } finally {
            state.openAiSettingsDeleting = false;
            renderOpenAiSettings();
            renderAutoResearch();
        }
    }

    async function saveOpenAiAdminKey() {
        const input = $('#openAiAdminKeyInput');
        const adminKey = input?.value.trim() || '';
        if (!adminKey) {
            focusOpenAiAdminKeyInput();
            throw new Error('Bạn chưa nhập OpenAI Admin API key.');
        }

        state.openAiAdminKeySaving = true;
        state.openAiSettingsError = '';
        state.openAiOfficialCostError = '';
        renderOpenAiSettings();
        try {
            const data = await api('openai_billing_admin_key_save', { adminApiKey: adminKey });
            state.openAiSettings = data;
            if (input) input.value = '';
            showToast('Đã lưu OpenAI Admin API key');
            autoSyncOpenAiOfficialCosts();
        } catch (error) {
            state.openAiSettingsError = error.message;
            throw error;
        } finally {
            state.openAiAdminKeySaving = false;
            renderOpenAiSettings();
        }
    }

    async function deleteOpenAiAdminKey() {
        if (!state.openAiSettings?.admin_api_key_configured) {
            showToast('Chưa có Admin API key để xóa');
            return;
        }
        if (!window.confirm('Xóa OpenAI Admin API key khỏi app local? Key gốc trên OpenAI Platform không bị revoke.')) {
            return;
        }

        state.openAiAdminKeyDeleting = true;
        state.openAiSettingsError = '';
        state.openAiOfficialCostError = '';
        renderOpenAiSettings();
        try {
            const data = await api('openai_billing_admin_key_delete', {});
            state.openAiSettings = data;
            const input = $('#openAiAdminKeyInput');
            if (input) input.value = '';
            showToast('Đã xóa OpenAI Admin API key khỏi app local');
        } catch (error) {
            state.openAiSettingsError = error.message;
            throw error;
        } finally {
            state.openAiAdminKeyDeleting = false;
            renderOpenAiSettings();
        }
    }

    async function syncOpenAiOfficialCosts(options = {}) {
        const silent = options.silent === true;
        if (!state.openAiSettings?.admin_api_key_configured) {
            if (!silent) focusOpenAiAdminKeyInput();
            throw new Error('Cần OpenAI Admin API key để đồng bộ chi phí chính thức.');
        }

        state.openAiOfficialCostSyncing = true;
        state.openAiOfficialCostError = '';
        renderOpenAiSettings();
        try {
            const data = await api('openai_billing_sync', {});
            state.openAiSettings = data;
            if (!silent) showToast('Đã đồng bộ chi phí chính thức từ OpenAI');
        } catch (error) {
            state.openAiOfficialCostError = error.message;
            throw error;
        } finally {
            state.openAiOfficialCostSyncing = false;
            renderOpenAiSettings();
        }
    }

    async function testOpenAiSettings() {
        state.openAiSettingsTesting = true;
        state.openAiSettingsError = '';
        state.openAiTestResult = null;
        renderOpenAiSettings();
        try {
            const data = await api('llm_gateway_test_call', {
                prompt_template_key: 'strategy.idea.generate',
                task_type: 'strategy_proposal',
                agent_role: 'StrategyAI',
                preferred_provider: 'openai_chat',
                variables: {
                    research_goal: 'Kiểm tra kết nối OpenAI cho app backtest chiến lược. Trả JSON ngắn với trạng thái ok và một ghi chú an toàn.',
                    strategy_context: JSON.stringify({ symbol: 'EURUSD', mode: 'connection_test', live_trading: false }),
                    memory_lessons: 'Không đặt lệnh MT5. Chỉ kiểm tra kết nối API.',
                },
            });
            state.openAiTestResult = data.llm_response || data.response || null;
            await loadOpenAiSettings();
            showToast(state.openAiTestResult?.ok ? 'OpenAI phản hồi thành công' : 'OpenAI trả lỗi, xem chi tiết trong màn kết nối');
        } catch (error) {
            state.openAiSettingsError = error.message;
            throw error;
        } finally {
            state.openAiSettingsTesting = false;
            renderOpenAiSettings();
        }
    }

    async function createStrategyDraftFromAIJob() {
        const job = selectedAIJob();
        if (!job) throw new Error('Chưa chọn AI job.');
        const data = await api('ai_job_create_strategy_draft', { id: job.id });
        state.aiJobs = data.ai_jobs || state.aiJobs;
        updateAIJobInState(data.ai_job);
        applyStrategyV2Response(data, data.strategy_version?.id || data.version?.id || '');
        showToast(data.created ? 'Đã tạo bản nháp chiến lược' : 'Đã mở bản nháp chiến lược');
        activateTab('strategy-versions');
        renderStrategyV2Manager();
    }

    function selectedAIJob() {
        return (state.aiJobs || []).find((job) => job.id === state.selectedAIJobId) || null;
    }

    function canCreateStrategyDraftFromAIJob(job) {
        if (!job || job.status !== 'Completed') return false;
        const type = job.job_type || job.jobType || '';
        if (!['GenerateStrategyIdea', 'ImproveStrategy'].includes(type)) return false;
        const output = job.output_json || job.outputJson || {};
        if (!output || typeof output !== 'object') return false;
        const parsed = output.parsed_output || output.parsedOutput || output.llm_response?.parsed || output.llmResponse?.parsed || {};
        return [output, parsed].some((payload) => Boolean(
            payload?.rules_json
                || payload?.rulesJson
                || payload?.rules
                || payload?.changed_rules_json
                || payload?.changedRulesJson
                || payload?.strategy_version_id
                || payload?.strategyVersionId
        ));
    }

    function updateAIJobInState(job) {
        if (!job?.id) return;
        const index = state.aiJobs.findIndex((item) => item.id === job.id);
        if (index >= 0) {
            state.aiJobs[index] = job;
        } else {
            state.aiJobs.unshift(job);
        }
    }

    function aiJobTypeLabel(type) {
        const labels = {
            GenerateStrategyIdea: 'Ý tưởng chiến lược',
            ImproveStrategy: 'Cải thiện chiến lược',
            AnalyzeBacktest: 'Phân tích backtest',
            EvaluateExperiment: 'Đánh giá thí nghiệm',
        };

        return labels[type] || String(type || '');
    }

    function renderPromptTemplates() {
        const list = $('#promptTemplateList');
        if (!list) return;

        const templates = state.promptTemplates || [];
        if (!state.selectedPromptTemplateId || !templates.some((template) => template.id === state.selectedPromptTemplateId)) {
            state.selectedPromptTemplateId = templates[0]?.id || '';
        }

        const summary = $('#promptTemplateSummary');
        if (summary) {
            const activeCount = templates.filter((template) => template.status === 'Active').length;
            summary.innerHTML = [
                memoryRow('Tong template', String(templates.length)),
                memoryRow('Dang kich hoat', String(activeCount)),
                memoryRow('Nguon', 'Prompt Manager foundation'),
            ].join('');
        }

        if (!templates.length) {
            const emptyText = state.promptTemplatesLoading
                ? 'Dang tai prompt templates...'
                : state.promptTemplatesError || 'Chua co prompt template.';
            list.innerHTML = `<div class="notice">${escapeHtml(emptyText)}</div>`;
        } else {
            list.innerHTML = templates.map(promptTemplateListItemHtml).join('');
        }

        $$('.prompt-template-item').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedPromptTemplateId = button.dataset.promptTemplateId || '';
                renderPromptTemplates();
            });
        });

        const selected = selectedPromptTemplate();
        renderPromptTemplateForm(selected);
        renderPromptGatewayTester(selected);
    }

    function promptTemplateListItemHtml(template) {
        const active = template.id === state.selectedPromptTemplateId ? ' is-active' : '';
        return `
            <button class="profile-item prompt-template-item${active}" type="button" data-prompt-template-id="${escapeAttr(template.id)}">
                <strong>${escapeHtml(template.template_key || template.templateKey || template.id)} v${escapeHtml(template.version || 1)} | ${escapeHtml(template.status || '')}</strong>
                <span>${escapeHtml(template.name || '')} | ${escapeHtml(template.owner_module || template.ownerModule || '')}</span>
                <span>${escapeHtml(template.output_format || template.outputFormat || '')} | ${escapeHtml(template.updated_at || template.updatedAt || '')}</span>
            </button>
        `;
    }

    function renderPromptTemplateForm(template) {
        const form = $('#promptTemplateForm');
        if (!form) return;

        $('#promptTemplateIdInput').value = template?.id || '';
        $('#promptTemplateKeyInput').value = template?.template_key || template?.templateKey || '';
        $('#promptTemplateVersionInput').value = template?.version || 1;
        $('#promptTemplateNameInput').value = template?.name || '';
        $('#promptTemplateDescriptionInput').value = template?.description || '';
        $('#promptTemplateOwnerInput').value = template?.owner_module || template?.ownerModule || '';
        $('#promptTemplateStatusInput').value = template?.status || 'Draft';
        $('#promptTemplateSystemInput').value = template?.system_prompt || template?.systemPrompt || '';
        $('#promptTemplateDeveloperInput').value = template?.developer_prompt || template?.developerPrompt || '';
        $('#promptTemplateUserInput').value = template?.user_prompt_template || template?.userPromptTemplate || '';
        $('#promptTemplateRequiredInput').value = promptVariableText(template?.required_variables_json || template?.requiredVariables || []);
        $('#promptTemplateOptionalInput').value = promptVariableText(template?.optional_variables_json || template?.optionalVariables || []);
        $('#promptTemplateOutputFormatInput').value = template?.output_format || template?.outputFormat || 'plain_text';
        $('#promptTemplateParserModeInput').value = template?.parser_mode || template?.parserMode || 'plain_text';
        $('#promptTemplateSafetyInput').value = template?.safety_level || template?.safetyLevel || 'normal';
        $('#promptTemplateNotesInput').value = template?.notes || '';

        const activateButton = $('#activatePromptTemplateBtn');
        const archiveButton = $('#archivePromptTemplateBtn');
        if (activateButton) {
            activateButton.disabled = !template || template.status === 'Active' || template.status === 'Archived';
        }
        if (archiveButton) {
            archiveButton.disabled = !template || template.status === 'Archived';
        }
    }

    async function loadPromptTemplates(preferredId = '') {
        state.promptTemplatesLoading = true;
        state.promptTemplatesError = '';
        renderPromptTemplates();

        try {
            const data = await api('prompt_template_list', { limit: 200 });
            state.promptTemplates = data.templates || [];
            state.promptTemplatesLoaded = true;
            state.selectedPromptTemplateId = preferredId && state.promptTemplates.some((template) => template.id === preferredId)
                ? preferredId
                : state.selectedPromptTemplateId || state.promptTemplates[0]?.id || '';
        } catch (error) {
            state.promptTemplatesError = error.message;
            throw error;
        } finally {
            state.promptTemplatesLoading = false;
            renderPromptTemplates();
        }
    }

    async function savePromptTemplate(event) {
        event.preventDefault();
        const payload = readPromptTemplateForm();
        const hasId = !!payload.id;
        const data = await api(hasId ? 'prompt_template_update' : 'prompt_template_create', payload);
        state.promptTemplates = data.templates || state.promptTemplates;
        updatePromptTemplateInState(data.template);
        state.selectedPromptTemplateId = data.template?.id || state.selectedPromptTemplateId;
        renderPromptTemplates();
        showToast(hasId ? 'Da cap nhat prompt template' : 'Da tao prompt template');
    }

    async function activatePromptTemplate() {
        const template = selectedPromptTemplate();
        if (!template) throw new Error('Chua chon prompt template.');
        const data = await api('prompt_template_activate', { id: template.id });
        state.promptTemplates = data.templates || state.promptTemplates;
        updatePromptTemplateInState(data.template);
        state.selectedPromptTemplateId = data.template?.id || state.selectedPromptTemplateId;
        renderPromptTemplates();
        showToast('Da kich hoat prompt template');
    }

    async function archivePromptTemplate() {
        const template = selectedPromptTemplate();
        if (!template) throw new Error('Chua chon prompt template.');
        const data = await api('prompt_template_archive', { id: template.id });
        state.promptTemplates = data.templates || state.promptTemplates;
        updatePromptTemplateInState(data.template);
        state.selectedPromptTemplateId = data.template?.id || state.selectedPromptTemplateId;
        renderPromptTemplates();
        showToast('Da luu tru prompt template');
    }

    function clearPromptTemplateForm() {
        state.selectedPromptTemplateId = '';
        renderPromptTemplateForm(null);
        $('#promptTemplateVersionInput').value = '1';
        $('#promptTemplateStatusInput').value = 'Draft';
        $('#promptTemplateOutputFormatInput').value = 'plain_text';
        $('#promptTemplateParserModeInput').value = 'plain_text';
        $('#promptTemplateSafetyInput').value = 'normal';
        renderPromptTemplates();
    }

    function readPromptTemplateForm() {
        const payload = {
            id: $('#promptTemplateIdInput').value.trim(),
            template_key: $('#promptTemplateKeyInput').value.trim(),
            version: Math.max(1, Math.round(Number($('#promptTemplateVersionInput').value) || 1)),
            name: $('#promptTemplateNameInput').value.trim(),
            description: $('#promptTemplateDescriptionInput').value.trim(),
            owner_module: $('#promptTemplateOwnerInput').value.trim(),
            status: $('#promptTemplateStatusInput').value,
            system_prompt: $('#promptTemplateSystemInput').value,
            developer_prompt: $('#promptTemplateDeveloperInput').value,
            user_prompt_template: $('#promptTemplateUserInput').value,
            required_variables_json: readPromptVariableList('promptTemplateRequiredInput'),
            optional_variables_json: readPromptVariableList('promptTemplateOptionalInput'),
            output_format: $('#promptTemplateOutputFormatInput').value,
            parser_mode: $('#promptTemplateParserModeInput').value,
            safety_level: $('#promptTemplateSafetyInput').value.trim() || 'normal',
            notes: $('#promptTemplateNotesInput').value.trim(),
        };

        if (!payload.id) {
            delete payload.id;
        }

        return payload;
    }

    function selectedPromptTemplate() {
        return (state.promptTemplates || []).find((template) => template.id === state.selectedPromptTemplateId) || null;
    }

    function updatePromptTemplateInState(template) {
        if (!template?.id) return;
        const index = state.promptTemplates.findIndex((item) => item.id === template.id);
        if (index >= 0) {
            state.promptTemplates[index] = template;
        } else {
            state.promptTemplates.unshift(template);
        }
    }

    function readPromptVariableList(id) {
        return String($(`#${id}`)?.value || '')
            .split(/[\n,]+/)
            .map((item) => item.trim())
            .filter(Boolean);
    }

    function promptVariableText(variables) {
        return Array.isArray(variables) ? variables.join(', ') : '';
    }

    function renderPromptGatewayTester(template) {
        const panel = $('#promptGatewayTestPanel');
        if (!panel) return;

        const templateId = template?.id || '';
        if (state.promptGatewayVariablesTemplateId !== templateId) {
            state.promptGatewayVariablesTemplateId = templateId;
            state.promptGatewaySampleVariables = JSON.stringify(buildSamplePromptVariables(template), null, 2);
            state.promptGatewayTestResult = null;
            state.promptGatewayTestError = '';
        }

        const input = $('#promptGatewayVariablesInput');
        if (input && document.activeElement !== input && input.value !== state.promptGatewaySampleVariables) {
            input.value = state.promptGatewaySampleVariables;
        }

        const providerSelect = $('#promptGatewayProviderSelect');
        if (providerSelect && providerSelect.value !== state.promptGatewayProvider) {
            providerSelect.value = state.promptGatewayProvider;
        }

        const button = $('#testPromptMockAiBtn');
        if (button) {
            button.disabled = state.promptGatewayTestLoading || !template || template.status !== 'Active';
            button.textContent = `Test prompt bang ${promptGatewayProviderLabel(state.promptGatewayProvider)}`;
        }

        const summary = $('#promptGatewayTestSummary');
        if (summary) {
            summary.innerHTML = [
                memoryRow('Template', promptTemplateKey(template) || 'Chua chon'),
                memoryRow('Trang thai', template?.status || 'Khong co'),
                memoryRow('Provider', promptGatewayProviderLabel(state.promptGatewayProvider)),
                memoryRow('Output', template?.output_format || template?.outputFormat || 'plain_text'),
            ].join('');
        }

        const result = $('#promptGatewayTestResult');
        if (!result) return;
        if (state.promptGatewayTestLoading) {
            result.innerHTML = `<div class="notice">Dang test prompt bang ${escapeHtml(promptGatewayProviderLabel(state.promptGatewayProvider))}...</div>`;
            return;
        }
        if (state.promptGatewayTestError) {
            result.innerHTML = `<div class="notice">${escapeHtml(state.promptGatewayTestError)}</div>`;
            return;
        }
        if (state.promptGatewayTestResult) {
            result.innerHTML = promptGatewayResultHtml(state.promptGatewayTestResult);
            return;
        }

        result.innerHTML = '<div class="notice">Chua co ket qua AI.</div>';
    }

    async function testPromptWithMockAI() {
        const template = selectedPromptTemplate();
        if (!template) throw new Error('Chua chon prompt template.');
        if (template.status !== 'Active') throw new Error('Prompt template can duoc Active truoc khi test.');

        let variables;
        try {
            variables = readPromptGatewayVariables();
        } catch (error) {
            state.promptGatewayTestError = error.message;
            renderPromptGatewayTester(template);
            throw error;
        }

        state.promptGatewayTestLoading = true;
        state.promptGatewayTestError = '';
        state.promptGatewayTestResult = null;
        renderPromptGatewayTester(template);

        try {
            const data = await api('llm_gateway_test_call', {
                prompt_template_key: promptTemplateKey(template),
                task_type: promptGatewayTaskType(template),
                agent_role: template.owner_module || template.ownerModule || 'PromptAI',
                output_format: template.output_format || template.outputFormat || 'plain_text',
                preferred_provider: state.promptGatewayProvider || 'mock',
                variables,
            });
            state.promptGatewayTestResult = data.llm_response || data.response || null;
            if (!state.promptGatewayTestResult) {
                throw new Error('LLM Gateway response is empty.');
            }
            showToast(state.promptGatewayTestResult.ok ? `${promptGatewayProviderLabel(state.promptGatewayProvider)} da phan hoi` : 'AI provider tra ve loi chuan hoa');
        } catch (error) {
            state.promptGatewayTestError = error.message;
            throw error;
        } finally {
            state.promptGatewayTestLoading = false;
            renderPromptGatewayTester(template);
        }
    }

    function readPromptGatewayVariables() {
        const input = $('#promptGatewayVariablesInput');
        const raw = input?.value || '{}';
        state.promptGatewaySampleVariables = raw;

        try {
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                throw new Error('Sample variables must be a JSON object.');
            }

            return parsed;
        } catch (error) {
            throw new Error('JSON bien mau khong hop le: ' + error.message);
        }
    }

    function promptGatewayResultHtml(response) {
        const usage = response.usage || {};
        const error = response.error ? promptGatewayErrorText(response.error) : 'Không có';
        return `
            <div class="strategy-v2-summary">
                ${memoryRow('Kết quả', response.ok ? 'Thành công' : 'Thất bại')}
                ${memoryRow('Provider', `${response.provider || ''} | ${response.model_name || ''}`)}
                ${memoryRow('Parser', `${response.parser_mode || ''} | ${response.validation_status || ''}`)}
                ${memoryRow('Token', `${number(usage.input_tokens || 0)} in / ${number(usage.output_tokens || 0)} out`)}
                ${memoryRow('Lỗi', error)}
            </div>
            <pre class="prompt-gateway-output">${escapeHtml(JSON.stringify(response, null, 2))}</pre>
        `;
    }

    function promptGatewayErrorText(error) {
        const category = String(error.category || 'error');
        const message = String(error.message || '');
        const lower = message.toLowerCase();

        if (category === 'ssl_certificate_error' || lower.includes('certificate')) {
            return 'PHP chưa nhận được chứng chỉ CA để xác minh OpenAI. App đã hỗ trợ CA bundle; nếu còn lỗi hãy kiểm tra file cacert.pem.';
        }
        if (category === 'rate_limit' && (lower.includes('no credits') || lower.includes('quota') || lower.includes('billing'))) {
            return 'Tài khoản OpenAI API chưa có credit hoặc đã hết credit. Vào Billing trên OpenAI Platform để nạp credit rồi test lại.';
        }
        if (category === 'authentication_error') {
            return 'API key không hợp lệ hoặc đã bị revoke. Hãy tạo key mới, lưu lại rồi test lại.';
        }
        if (category === 'budget_exceeded') {
            return 'App đã chạm giới hạn chi phí OpenAI trong ngày. Tăng ngân sách/ngày hoặc đợi sang ngày mới.';
        }

        return `${category}: ${message}`;
    }

    function buildSamplePromptVariables(template) {
        if (!template) return {};
        const names = [
            ...(Array.isArray(template.required_variables_json || template.requiredVariables) ? (template.required_variables_json || template.requiredVariables) : []),
            ...(Array.isArray(template.optional_variables_json || template.optionalVariables) ? (template.optional_variables_json || template.optionalVariables) : []),
        ];
        const variables = {};
        names.forEach((name) => {
            variables[name] = samplePromptValue(name);
        });

        return variables;
    }

    function samplePromptValue(name) {
        const samples = {
            research_goal: 'Tim y tuong chien luoc co sample ro rang va giu ket qua dang de xuat.',
            strategy_context: {
                strategy_version_id: 'sv_mock_001',
                symbol: 'EURUSD',
                timeframe: 'H4/M15',
                rule_summary: 'Vùng canh buy/sell H4, M15 confirmation, fixed RR.',
            },
            memory_lessons: ['Uu tien sample du lon.', 'Khong tu dong doi logic giao dich.'],
            current_rules: {
                riskPercent: 1,
                minRR: 3,
                requireH4Trend: true,
            },
            backtest_summary: {
                strategyVersionId: 'sv_mock_001',
                totalTrades: 80,
                winRate: 52.5,
                profitFactor: 1.42,
                maxDrawdownPercent: 6.4,
            },
            diagnostics: {
                sampleStatus: 'OK',
                skippedReasons: {
                    NO_H4_ZONE: 4,
                    BAD_RR: 2,
                },
            },
            strategy_version_id: 'sv_mock_001',
            known_weaknesses: 'Drawdown tap trung khi thi truong sideway.',
            experiment_summary: 'So sanh ba ung vien mock tren validation split.',
            candidates: [
                { id: 'candidate_a', profitFactor: 1.42, maxDrawdownPercent: 6.4 },
                { id: 'candidate_b', profitFactor: 1.35, maxDrawdownPercent: 5.1 },
            ],
            selection_criteria: 'Uu tien profit factor, drawdown thap, va sample du lon.',
        };

        return Object.prototype.hasOwnProperty.call(samples, name) ? samples[name] : `sample_${name}`;
    }

    function promptTemplateKey(template) {
        return template?.template_key || template?.templateKey || '';
    }

    function promptGatewayTaskType(template) {
        const key = promptTemplateKey(template);
        const map = {
            'strategy.idea.generate': 'strategy_proposal',
            'strategy.version.improve': 'strategy_proposal',
            'backtest.summary': 'backtest_summary',
            'experiment.compare': 'optimizer_compare',
        };

        return map[key] || slug(key || 'prompt_test');
    }

    function promptGatewayProviderLabel(provider) {
        if (provider === 'openai_chat') return 'OpenAI Chat';
        if (provider === 'ollama') return 'Ollama Local';

        return 'Mock AI';
    }

    function renderCalibrationReview() {
        const versionSelect = $('#calibrationVersionSelect');
        if (!versionSelect) return;
        const currentVersion = versionSelect.value || state.selectedStrategyVersionId || '';
        versionSelect.innerHTML = strategyVersionOptions(currentVersion);
        versionSelect.value = currentVersion;

        const symbolSelect = $('#calibrationSymbolSelect');
        if (symbolSelect) {
            const currentSymbol = symbolSelect.value;
            symbolSelect.innerHTML = symbolOptions();
            if (currentSymbol && [...symbolSelect.options].some((option) => option.value === currentSymbol)) {
                symbolSelect.value = currentSymbol;
            }
        }

        const presetSelect = $('#calibrationPresetSelect');
        if (presetSelect) {
            const currentPreset = presetSelect.value || 'loose-debug';
            presetSelect.innerHTML = calibrationPresetOptions(currentPreset);
            presetSelect.value = [...presetSelect.options].some((option) => option.value === currentPreset) ? currentPreset : 'loose-debug';
        }

        const queueType = $('#calibrationQueueTypeSelect')?.value || state.calibrationQueueType || 'ZONE_ALREADY_BROKEN';
        state.calibrationQueueType = queueType;
        const total = state.calibrationQueue.length;
        const reviewed = state.calibrationQueue.filter((item) => item.reviewed).length;
        $('#calibrationProgress').textContent = `Đã xem ${number(reviewed)} / ${number(total)}`;
        $('#calibrationPosition').textContent = total ? `${number(state.calibrationIndex + 1)} / ${number(total)}` : '0 / 0';
        $('#calibrationQueueList').innerHTML = total
            ? state.calibrationQueue.map((item, index) => calibrationQueueRowHtml(item, index)).join('')
            : '<div class="notice">Hãy tải một hàng chờ để bắt đầu hiệu chỉnh thủ công.</div>';
        $('#calibrationItemDetail').innerHTML = total
            ? calibrationItemDetailHtml(state.calibrationQueue[state.calibrationIndex])
            : '<div class="notice">Chưa chọn mục nào để hiệu chỉnh.</div>';
        $('#calibrationReportOutput').innerHTML = state.calibrationReport ? calibrationReportHtml(state.calibrationReport) : '<div class="notice">Chưa có báo cáo hiệu chỉnh.</div>';
        $('#calibrationAdvisorOutput').innerHTML = state.calibrationAdvisor ? calibrationAdvisorHtml(state.calibrationAdvisor) : '<div class="notice">Chưa có gợi ý điều chỉnh luật.</div>';
        decorateCalibrationReview();
    }

    function calibrationPresetOptions(selected = 'loose-debug') {
        const presets = ['strict', 'balanced', 'loose-debug', ...((state.calibratedPresets || []).map((preset) => preset.name).filter(Boolean))];
        return [...new Set(presets)]
            .map((preset) => `<option value="${escapeAttr(preset)}" ${preset === selected ? 'selected' : ''}>${escapeHtml(translatePresetName(preset))}</option>`)
            .join('');
    }

    function calibrationQueueRowHtml(item, index) {
        const active = index === state.calibrationIndex ? ' is-active' : '';
        const reviewed = item.reviewed ? ' reviewed' : '';
        const title = item.reasonCode ? termLabelHtml(item.reasonCode, translateReasonCode(item.reasonCode)) : escapeHtml(translateDisplayText(item.title || item.targetId || ''));
        return `
            <button class="review-item calibration-queue-row${active}${reviewed}" type="button" data-calibration-index="${escapeAttr(index)}">
                <strong>${title}</strong>
                <span>${escapeHtml(formatDisplayLabel(item.reviewType || '', 'reviewTypes'))} | ${escapeHtml(item.symbol || '')} ${escapeHtml(item.timeframe || '')} | ${escapeHtml(item.latestReview?.label ? translateReviewLabel(item.latestReview.label) : 'Chưa chấm')}</span>
            </button>
        `;
    }

    function calibrationItemDetailHtml(item) {
        const snapshot = item.snapshotData || {};
        const zone = snapshot.zone || snapshot.candidate?.zone || {};
        const candidate = snapshot.candidate || {};
        const trade = snapshot.trade || {};
        const latest = item.latestReview || null;
        const labels = calibrationLabelsForType(item.reviewType);
        const reason = item.reasonCode || snapshot.reasonCode || candidate.rejectReason || trade.reasonCode || '';
        return `
            <div class="memory-summary">
                ${memoryRow('Đối tượng', `${formatDisplayLabel(item.reviewType || '', 'reviewTypes')} | ${item.targetId || ''}`)}
                ${memoryRow('Cặp tiền / khung thời gian', `${item.symbol || ''} ${item.timeframe || ''}`)}
                ${memoryRow('Thời gian', item.time || candidate.time || trade.entryTime || '')}
                ${memoryRow('Lý do của hệ thống', `${translateReasonCode(reason)} | ${translateDisplayText(item.subtitle || candidate.reason || trade.reasonSkipped || trade.reasonEnter || '')}`)}
                ${memoryRow('Vùng giá', zone && Object.keys(zone).length ? `${displayZone(zone.type || candidate.zoneType || trade.zoneType || '')} ${zone.low ?? candidate.zoneLow ?? ''} - ${zone.high ?? candidate.zoneHigh ?? ''} | ${zone.id || candidate.zoneId || trade.zoneId || ''}` : 'Không có')}
                ${memoryRow('Vòng đời vùng giá', zoneLifecycleText(zone, candidate.zoneStateAtCandidate))}
                ${memoryRow('Điểm vào / SL / TP', entrySlTpText(candidate, trade))}
                ${memoryRow('Nhãn gần nhất', latest ? `${translateReviewLabel(latest.label)} | ${latest.note || ''}` : 'Chưa có')}
            </div>
            <h4>Chi tiết điểm độ mạnh</h4>
            <div class="review-list compact">${strengthBreakdownHtml(candidate.strengthBreakdown || zone.strengthBreakdown || {})}</div>
            <h4>Vòng đời vùng giá</h4>
            <div class="review-list compact">${zoneLifecycleRowsHtml(zone, candidate.zoneStateAtCandidate)}</div>
            <h4>Nến M15 quanh mục đang xem</h4>
            <div class="review-list compact">${candlesHtml(snapshot.nearbyM15Candles || candidate.nearbyM15Candles || [])}</div>
            <h4>Nến H4 quanh vùng giá</h4>
            <div class="review-list compact">${candlesHtml(snapshot.nearbyH4Candles || [])}</div>
            <div class="manual-review-box">
                <textarea id="calibrationManualNote" rows="3" placeholder="Ghi chú thủ công">${escapeHtml(latest?.note || '')}</textarea>
                <div class="manual-review-actions">
                    ${labels.map((label) => `<button class="chart-control" type="button" data-calibration-label="${escapeAttr(label)}">${termLabelHtml(label, translateReviewLabel(label))}</button>`).join('')}
                    <button class="chart-control" type="button" id="calibrationFocusChartBtn">Xem trên biểu đồ</button>
                </div>
            </div>
        `;
    }

    function calibrationLabelsForType(type) {
        const fallback = {
            zone: ['ZONE_GOOD', 'ZONE_BAD', 'ZONE_TRADEABLE', 'ZONE_NOT_TRADEABLE', 'BROKEN_CORRECT', 'BROKEN_WRONG', 'STRENGTH_CORRECT', 'STRENGTH_TOO_LOW', 'STRENGTH_TOO_HIGH', 'ORIGIN_CORRECT', 'ORIGIN_WRONG'],
            candidate: ['REJECT_CORRECT', 'REJECT_WRONG', 'SHOULD_HAVE_BEEN_TRADE', 'NO_M15_CONFIRM_CORRECT', 'NO_M15_CONFIRM_WRONG', 'BAD_RR_CORRECT', 'BAD_RR_WRONG', 'DIRTY_SIDEWAY_CORRECT', 'DIRTY_SIDEWAY_WRONG', 'LIMIT_TOO_DEEP', 'LIMIT_REASONABLE', 'ENTRY_WRONG', 'SL_WRONG', 'TP_WRONG'],
            trade: ['ENTRY_GOOD', 'ENTRY_BAD', 'SL_GOOD', 'SL_BAD', 'TP_GOOD', 'TP_BAD', 'SHOULD_NOT_TRADE', 'GOOD_LOSS', 'BAD_LOSS', 'GOOD_WIN', 'LUCKY_WIN'],
        };
        return state.manualReviewLabels?.[type] || fallback[type] || [];
    }

    function zoneLifecycleText(zone, stateAtCandidate = null) {
        const z = stateAtCandidate || zone || {};
        if (!z || !Object.keys(z).length) return 'Không có';
        return `Trạng thái: ${formatDisplayLabel(z.lifecycleStatus || (z.broken ? 'broken' : z.fresh ? 'fresh' : z.touched ? 'touched' : 'active'), 'zoneStates')} | Đang hiệu lực: ${displayBoolean(!!z.active)} | Vùng mới: ${displayBoolean(!!z.fresh)} | Đã chạm: ${displayBoolean(!!z.touched)} | Bị yếu: ${displayBoolean(!!z.weakened)} | Đã bị phá: ${displayBoolean(!!z.broken)} | Số lần chạm: ${z.touchedCount ?? 0}`;
    }

    function zoneLifecycleRowsHtml(zone, stateAtCandidate = null) {
        const z = stateAtCandidate || zone || {};
        if (!z || !Object.keys(z).length) return '<div class="notice">Chưa có ảnh chụp vòng đời vùng giá.</div>';
        return [
            memoryRow('Trạng thái', zoneLifecycleText(zone, stateAtCandidate)),
            memoryRow('Thời điểm tạo / điểm gốc', `${zone.createdAt || ''} | ${zone.originCandleTime || ''}`),
            memoryRow('Đã bị phá', z.broken ? `${z.brokenTime || zone.brokenTime || ''} | ${translateDisplayText(z.brokenReason || zone.brokenReason || '')}` : 'Không'),
            memoryRow('Các lần chạm', (zone.touches || []).slice(0, 6).map((touch) => touch.time || '').join(' | ') || 'Chưa có'),
        ].join('');
    }

    function entrySlTpText(candidate, trade) {
        const entry = candidate.proposedEntry ?? trade.proposedEntry ?? trade.entryPrice ?? '';
        const sl = candidate.proposedSL ?? trade.proposedSL ?? trade.stopLoss ?? '';
        const tp = candidate.proposedTP ?? trade.proposedTP ?? trade.takeProfit ?? '';
        return `${entry} / ${sl} / ${tp}`;
    }

    function candlesHtml(candles) {
        return (candles || []).length
            ? candles.map((bar) => `<div class="review-item"><strong>${escapeHtml(bar.time || '')}</strong><span>${escapeHtml(candleText(bar))}</span></div>`).join('')
            : '<div class="notice">Chưa lưu nến nào cho mục này.</div>';
    }

    function calibrationReportHtml(report) {
        return [
            memoryRow('Tổng số mẫu đã chấm', report.manualReviewsTotal ?? 0),
            memoryRow('Theo loại', displayCountMap(report.reviewTypeCounts || {}, 'reviewTypes')),
            memoryRow('Nhãn xuất hiện nhiều nhất', Object.entries(report.labelCounts || {}).slice(0, 8).map(([key, value]) => `${translateReviewLabel(key)}: ${number(value)}`).join(', ') || 'Chưa có'),
            memoryRow('Đánh giá vùng giá', `Đánh dấu bị phá sai: ${formatFixed((report.zoneReviews?.brokenWrongRate || 0) * 100, 1)}% | Chấm độ mạnh quá thấp: ${formatFixed((report.zoneReviews?.strengthTooLowRate || 0) * 100, 1)}% | Xác định điểm gốc sai: ${formatFixed((report.zoneReviews?.originWrongRate || 0) * 100, 1)}%`),
            memoryRow('Đánh giá mẫu ứng viên', `Từ chối sai: ${formatFixed((report.candidateReviews?.rejectWrongRate || 0) * 100, 1)}% | Xác nhận M15 sai: ${formatFixed((report.candidateReviews?.noM15ConfirmWrongRate || 0) * 100, 1)}% | Đánh giá RR sai: ${formatFixed((report.candidateReviews?.badRrWrongRate || 0) * 100, 1)}%`),
            memoryRow('Đánh giá lệnh', `Điểm vào chưa tốt: ${formatFixed((report.tradeReviews?.entryBadRate || 0) * 100, 1)}% | Stop Loss chưa tốt: ${formatFixed((report.tradeReviews?.slBadRate || 0) * 100, 1)}% | Take Profit chưa tốt: ${formatFixed((report.tradeReviews?.tpBadRate || 0) * 100, 1)}%`),
        ].join('');
    }

    function calibrationAdvisorHtml(advisor) {
        const recs = advisor.recommendations || [];
        if (!recs.length) return '<div class="notice">Chưa có đề xuất nào từ các nhãn chấm thủ công hiện tại.</div>';
        return recs
            .map((item) => `
                <div class="review-item">
                    <strong>${escapeHtml(formatDisplayLabel(item.area || '', 'advisorAreas'))} | ${escapeHtml(formatDisplayLabel(item.field || '', 'ruleFields'))}</strong>
                    <span>Hiện tại: ${escapeHtml(displayAdvisorValue(item.currentValue))} → Đề xuất: ${escapeHtml(displayAdvisorValue(item.suggestedValue))}<br>${escapeHtml(translateDisplayText(item.reason || ''))} | Độ tin cậy: ${escapeHtml(item.confidence ?? '')} | ${item.requiresUserApproval ? 'Cần người dùng phê duyệt' : 'Không cần phê duyệt'}</span>
                </div>
            `)
            .join('');
    }

    function renderStrategyEvolution() {
        const parentSelect = $('#evolutionParentSelect');
        if (!parentSelect) return;
        parentSelect.innerHTML = strategyVersionOptions(state.selectedStrategyVersionId);
        parentSelect.value = state.selectedStrategyVersionId || '';
        $('#evolutionSymbolSelect').innerHTML = symbolOptions();

        const latest = state.lastEvolutionResult;
        const proposal = latest?.proposal || state.strategyUpgradeProposals?.[0];
        const evaluation = latest?.evaluation || state.strategyEvaluations?.[0];
        $('#evolutionStatus').textContent = evaluation ? (evaluation.passed ? 'ĐẠT' : 'KHÔNG ĐẠT') : '';
        if (!proposal) {
            $('#evolutionOutput').innerHTML = '<div class="notice">Chưa có đề xuất nào. Hãy chạy TradeBrain nâng cấp chiến lược để tạo phiên bản mới.</div>';
            return;
        }

        $('#evolutionOutput').innerHTML = `
            <div class="memory-summary">
                ${memoryRow('Mã đề xuất', proposal.id || '')}
                ${memoryRow('Chiến lược gốc', proposal.parentStrategyVersionId || '')}
                ${memoryRow('Phiên bản mới', proposal.newStrategyVersionId || '')}
                ${memoryRow('Trạng thái', translateStatus(proposal.status || ''))}
                ${memoryRow('Tác động dự kiến', translateDisplayText(proposal.expectedImpact || ''))}
                ${memoryRow('Cảnh báo rủi ro', translateDisplayText(proposal.riskWarning || ''))}
            </div>
            <h3>Chẩn đoán</h3>
            <div class="review-list">${(proposal.diagnosis || []).map((item) => `<div class="review-item"><span>${escapeHtml(translateDisplayText(item))}</span></div>`).join('')}</div>
            <h3>Bài học</h3>
            <div class="review-list">${(proposal.lessons || []).map((item) => `<div class="review-item"><strong>${escapeHtml(translateDisplayText(item.title || ''))}</strong><span>${escapeHtml(translateDisplayText(item.content || ''))} | Độ tin cậy: ${escapeHtml(item.confidence ?? 0)}</span></div>`).join('')}</div>
            <h3>Đề xuất thay đổi</h3>
            <div class="review-list">${(proposal.proposedChanges || []).map((item) => `<div class="review-item"><strong>${escapeHtml(formatDisplayLabel(item.field || '', 'ruleFields'))}</strong><span>${escapeHtml(displayAdvisorValue(item.oldValue))} → ${escapeHtml(displayAdvisorValue(item.newValue))}<br>${escapeHtml(translateDisplayText(item.reason || ''))}</span></div>`).join('')}</div>
            ${evaluation ? evaluationHtml(evaluation) : ''}
        `;
    }

    function renderStrategyCompare() {
        const list = $('#strategyCompareList');
        const diffView = $('#strategyDiffView');
        if (!list || !diffView) return;
        const evaluations = state.strategyEvaluations || [];
        if (!evaluations.length) {
            list.innerHTML = '<div class="notice">Chưa có kết quả đánh giá. Hãy chạy Tiến hóa chiến lược trước.</div>';
            diffView.innerHTML = '';
            $('#strategyDiffCount').textContent = '';
            return;
        }

        if (!state.selectedCompareEvaluationId || !evaluations.some((item) => item.id === state.selectedCompareEvaluationId)) {
            state.selectedCompareEvaluationId = evaluations[0].id;
        }
        list.innerHTML = evaluations
            .map((evaluation) => {
                const active = evaluation.id === state.selectedCompareEvaluationId ? ' is-active' : '';
                return `
                    <button class="profile-item compare-item${active}" type="button" data-evaluation-id="${escapeAttr(evaluation.id)}">
                        <strong>${escapeHtml(evaluation.passed ? 'ĐẠT' : 'KHÔNG ĐẠT')} | ${escapeHtml(evaluation.id)}</strong>
                        <span>${escapeHtml(evaluation.parentStrategyVersionId)} → ${escapeHtml(evaluation.newStrategyVersionId)} | Điểm ${escapeHtml(evaluation.scoreParent)} → ${escapeHtml(evaluation.scoreNew)}</span>
                    </button>
                `;
            })
            .join('');
        $$('.compare-item').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedCompareEvaluationId = button.dataset.evaluationId;
                renderStrategyCompare();
            });
        });

        const selected = evaluations.find((item) => item.id === state.selectedCompareEvaluationId) || evaluations[0];
        const parent = findStrategyVersion(selected.parentStrategyVersionId);
        const child = findStrategyVersion(selected.newStrategyVersionId);
        const diff = parent && child ? diffObjects(parent.rulesJson || {}, child.rulesJson || {}) : [];
        $('#strategyDiffCount').textContent = `${diff.length} thay đổi`;
        diffView.innerHTML = `
            ${evaluationHtml(selected)}
            <div class="review-list">
                ${diff.length ? diff.map((item) => `<div class="review-item"><strong>${escapeHtml(formatDisplayLabel(item.field, 'ruleFields'))}</strong><span>${escapeHtml(displayAdvisorValue(item.oldValue))} → ${escapeHtml(displayAdvisorValue(item.newValue))}</span></div>`).join('') : '<div class="notice">Không có khác biệt trong bộ luật.</div>'}
            </div>
        `;
    }

    function renderTradeBrainMemory() {
        const list = $('#tradebrainMemoryList');
        if (!list) return;
        const type = $('#tradebrainMemoryTypeFilter')?.value || '';
        const version = $('#tradebrainMemoryVersionFilter')?.value || '';
        const versionFilter = $('#tradebrainMemoryVersionFilter');
        if (versionFilter) {
            const current = versionFilter.value;
            versionFilter.innerHTML = '<option value="">Tất cả phiên bản</option>' + strategyVersionOptions(current, true);
            versionFilter.value = current;
        }

        const items = (state.tradebrainMemory || []).filter((item) => {
            if (type && item.type !== type) return false;
            if (version && item.relatedStrategyVersionId !== version) return false;
            return true;
        });
        list.innerHTML = items.length
            ? items
                  .map((item) => `
                    <div class="memory-row">
                        <strong>${escapeHtml(translateDisplayText(item.title || ''))}</strong>
                        <span>${escapeHtml(formatDisplayLabel(item.type || '', 'memoryTypes'))} | Độ tin cậy: ${escapeHtml(item.confidence ?? 0)} | ${escapeHtml(item.relatedStrategyVersionId || '')}<br>${escapeHtml(translateDisplayText(item.content || ''))}</span>
                    </div>
                  `)
                  .join('')
            : '<div class="notice">Chưa có bài học TradeBrain phù hợp bộ lọc.</div>';

        $('#tradebrainDecisionCount').textContent = `${(state.aiDecisions || []).length}`;
        $('#tradebrainDecisionList').innerHTML = (state.aiDecisions || []).length
            ? state.aiDecisions
                  .map((item) => `
                    <div class="review-item">
                        <strong>${escapeHtml(formatDisplayLabel(item.decisionType || '', 'decisionTypes'))}</strong>
                        <span>${escapeHtml(item.relatedStrategyVersionId || '')} | ${escapeHtml(item.relatedBacktestRunId || '')} | ${escapeHtml(item.createdAt || '')}</span>
                    </div>
                  `)
                  .join('')
            : '<div class="notice">Chưa có nhật ký quyết định.</div>';
    }

    function strategyVersionOptions(selectedId = '', omitEmpty = false) {
        const empty = omitEmpty ? '' : '<option value="">Chọn phiên bản chiến lược</option>';
        return (
            empty +
            (state.strategyVersions || [])
                .map((version) => `<option value="${escapeAttr(version.id)}" ${version.id === selectedId ? 'selected' : ''}>${escapeHtml(displayStrategyVersionName(version.name))} | ${escapeHtml(translateStatus(version.status))}</option>`)
                .join('')
        );
    }

    function symbolOptions() {
        const symbols = (selectedStrategyVersion()?.rulesJson?.symbols || state.strategy?.symbols || []).filter(Boolean);
        const unique = [...new Set(symbols)];
        return unique.map((symbol) => `<option value="${escapeAttr(symbol)}">${escapeHtml(symbol)}</option>`).join('');
    }

    function serverBacktestMetricHtml(summary) {
        return [
            backtestMetricHtml('Phần dữ liệu', formatDisplayLabel(summary.dataSplit || summary.rangeType || '', 'dataSplits')),
            backtestMetricHtml('Số lệnh', number(summary.totalTrades || 0)),
            backtestMetricHtml('Tỷ lệ thắng', `${formatFixed(summary.winRate || 0, 2)}%`),
            backtestMetricHtml('% lợi nhuận ròng', `${formatSigned(summary.netProfitPercent || 0, 2)}%`),
            backtestMetricHtml('% sụt giảm tối đa', `${formatFixed(summary.maxDrawdownPercent || 0, 2)}%`),
            backtestMetricHtml('Hệ số lợi nhuận', formatFixed(summary.profitFactor || 0, 2)),
            backtestMetricHtml('RR trung bình', formatSigned(summary.averageRR || 0, 2)),
            backtestMetricHtml('Chuỗi lệnh thua', number(summary.longestLossStreak || 0)),
            backtestMetricHtml('Trạng thái mẫu', translateSampleStatus(summary.result || summary.sampleStatus || 'OK')),
            backtestMetricHtml('Bộ thiết lập', translatePresetName(summary.rulesPreset || 'current')),
        ].join('');
    }

    function serverDiagnosticsHtml(result) {
        const summary = result.summary || result || {};
        const d = summary.diagnostics || result.diagnostics || {};
        if (!d || !Object.keys(d).length) {
            return '<div class="notice">Mức thông tin kiểm tra hiện tại không bật phần chẩn đoán.</div>';
        }
        const h4Zones = allDebugZones(result);
        const warnings = (summary.warnings || []).map((item) => `<div class="notice">${escapeHtml(translateDisplayText(item))}</div>`).join('');
        return `
            ${warnings}
            <h3>Tóm tắt chẩn đoán</h3>
            <div class="memory-summary">
                ${memoryRow('Cặp tiền', d.symbol || summary.symbol || '')}
                ${memoryRow('Phần dữ liệu', formatDisplayLabel(d.dataSplit || summary.dataSplit || '', 'dataSplits'))}
                ${memoryRow('Khoảng ngày', `${d.dateRange?.start || summary.startDate || ''} → ${d.dateRange?.end || summary.endDate || ''}`)}
                ${memoryRow('Số nến M15', number(d.totalM15Candles || 0))}
                ${memoryRow('Số nến H4', number(d.totalH4Candles || 0))}
                ${memoryRow('Số swing H4', number(d.h4SwingCount || 0))}
                ${memoryRow('Số swing M15', number(d.m15SwingCount || 0))}
                ${memoryRow('Số vùng canh sell H4', number(d.h4SupplyZones || 0))}
                ${memoryRow('Số vùng canh buy H4', number(d.h4DemandZones || 0))}
                ${memoryRow('Đang hiệu lực / vùng mới / đã chạm', `${number(d.zoneActive || 0)} / ${number(d.zoneFresh || 0)} / ${number(d.zoneTouched || 0)}`)}
                ${memoryRow('Bị yếu / đã bị phá / đủ mạnh', `${number(d.zoneWeakened || 0)} / ${number(d.zoneBroken || 0)} / ${number(d.zoneEnoughStrength || 0)}`)}
                ${memoryRow('Giá quay lại vùng H4', number(d.priceReturnsToH4Zone || 0))}
                ${memoryRow('Mẫu đã xét / bị từ chối', `${number(d.setupsConsidered || 0)} / ${number(d.setupsRejected || 0)}`)}
                ${memoryRow('Lệnh chờ tạo / khớp / hủy', `${number(d.pendingLimitCreated || 0)} / ${number(d.pendingLimitFilled || 0)} / ${number(d.pendingLimitCanceled || 0)}`)}
                ${memoryRow('Số lệnh mở | TP / SL / hòa vốn', `${number(d.tradesOpened || 0)} | ${number(d.tpCount || 0)} / ${number(d.slCount || 0)} / ${number(d.beCount || 0)}`)}
            </div>
            <h3>Chi tiết lý do bỏ qua</h3>
            <div class="review-list">${reasonRowsHtml(d.skippedReasons || {})}</div>
            ${zoneReviewHtml(result, h4Zones)}
        `;
    }

    function reasonRowsHtml(items) {
        const entries = Object.entries(items || {});
        if (!entries.length) return '<div class="notice">Chưa có lý do bỏ qua.</div>';
        entries.sort((a, b) => Number(b[1]) - Number(a[1]));
        return entries.map(([key, value]) => `<div class="review-item"><strong>${termLabelHtml(key, translateReasonCode(key))}</strong><span>${number(value)}</span></div>`).join('');
    }

    function allDebugZones(result) {
        return (result?.debug?.h4Zones || []).filter((zone) => zone && typeof zone === 'object');
    }

    function zoneReviewHtml(result, zones) {
        const filter = state.h4ZoneReviewFilter || 'active';
        const filtered = zones.filter((zone) => zoneMatchesFilter(zone, filter));
        const selected = zones.find((zone) => (zone.id || zone.zoneId) === state.selectedH4ZoneId) || filtered[0] || zones[0] || null;
        const filters = ['active', 'fresh', 'touched', 'weakened', 'broken', 'strong', 'weak'];
        return `
            <h3>Rà soát vùng H4</h3>
            <div class="review-toolbar">
                ${filters.map((item) => `<button class="chart-control ${filter === item ? 'is-active' : ''}" type="button" data-zone-filter="${escapeAttr(item)}">${termLabelHtml(item, formatDisplayLabel(item, 'zoneStates'))}</button>`).join('')}
                <span>${number(filtered.length)} / ${number(zones.length)} vùng</span>
            </div>
            <div class="zone-review-grid">
                <div class="review-list zone-review-list">${filtered.length ? filtered.slice(0, 300).map(zoneRowHtml).join('') : '<div class="notice">Không có vùng H4 nào phù hợp bộ lọc này.</div>'}</div>
                <div class="zone-review-detail">${selected ? zoneDetailHtml(result, selected) : '<div class="notice">Không có vùng H4 trong dữ liệu kiểm tra.</div>'}</div>
            </div>
        `;
    }

    function zoneMatchesFilter(zone, filter) {
        const strength = Number(zone.strengthScore || 0);
        if (filter === 'fresh') return !!zone.fresh;
        if (filter === 'touched') return !!zone.touched && !zone.broken;
        if (filter === 'weakened') return !!zone.weakened;
        if (filter === 'broken') return !!zone.broken;
        if (filter === 'strong') return strength >= 65;
        if (filter === 'weak') return strength < 65;
        return !!zone.active && !zone.broken;
    }

    function zoneRowHtml(zone) {
        const id = zone.id || zone.zoneId || '';
        const status = zone.lifecycleStatus || (zone.broken ? 'broken' : zone.fresh ? 'fresh' : zone.touched ? 'touched' : 'active');
        const active = id && id === state.selectedH4ZoneId ? ' is-active' : '';
        return `
            <button class="review-item zone-review-row${active}" type="button" data-zone-id="${escapeAttr(id)}">
                <strong>${escapeHtml(displayZone(zone.type || ''))} | ${escapeHtml(zone.id || '')}</strong>
                <span>${escapeHtml(zone.originCandleTime || zone.createdAt || '')} | ${escapeHtml(zone.low ?? '')} - ${escapeHtml(zone.high ?? '')} | Độ mạnh ${escapeHtml(zone.strengthScore ?? '')} | ${termLabelHtml(status, formatDisplayLabel(status, 'zoneStates'))} | Số lần chạm ${escapeHtml(zone.touchedCount ?? 0)}</span>
            </button>
        `;
    }

    function zoneDetailHtml(result, zone) {
        const id = zone.id || zone.zoneId || '';
        const relatedCandidates = setupCandidates(result).filter((candidate) => (candidate.zoneId || candidate.h4ZoneId || '') === id);
        const rejectSummary = countBy(relatedCandidates.filter((item) => item.rejectReason).map((item) => item.rejectReason));
        const latestReview = (state.zoneManualReviews || []).find((item) => item.zoneId === id);
        return `
            <div class="memory-summary">
                ${memoryRow('Vùng giá', `${zone.symbol || ''} ${zone.timeframe || ''} ${displayZone(zone.type || '')} ${id}`)}
                ${memoryRow('Khoảng giá', `${zone.low ?? ''} - ${zone.high ?? ''}`)}
                ${memoryRow('Trạng thái', zoneLifecycleText(zone))}
                ${memoryRow('Thời điểm tạo / điểm gốc', `${zone.createdAt || ''} | ${zone.originCandleTime || ''}`)}
                ${memoryRow('OHLC nến gốc', candleText(zone.originCandle || zone))}
                ${memoryRow('Lực đẩy', `${escapeHtml(zone.impulseStrength ?? '')} | ${(zone.impulseCandles || []).length} nến`)}
                ${memoryRow('Được tạo bởi BOS', displayBoolean(!!zone.createdByBos))}
                ${memoryRow('Nến BOS', candleText(zone.bosCandle || zone.createdByBosCandle || {}))}
                ${memoryRow('Lần chạm đầu / số lần chạm', `${zone.firstTouchTime || 'Chưa có'} / ${zone.touchedCount ?? 0}`)}
                ${memoryRow('Đã bị phá', zone.broken ? `${zone.brokenTime || ''} | ${translateDisplayText(zone.brokenReason || '')}` : 'Không')}
                ${memoryRow('Nhãn thủ công gần nhất', latestReview ? `${translateReviewLabel(latestReview.label)} | ${latestReview.note || ''}` : 'Chưa có')}
            </div>
            <h4>Chi tiết điểm độ mạnh</h4>
            <div class="review-list compact">${strengthBreakdownHtml(zone.strengthBreakdown || {})}</div>
            <h4>Các lần chạm</h4>
            <div class="review-list compact">${(zone.touches || []).slice(0, 12).map((touch) => `<div class="review-item"><strong>${escapeHtml(touch.time || '')}</strong><span>${escapeHtml(candleText(touch.candle || {}))}</span></div>`).join('') || '<div class="notice">Chưa có lần chạm nào.</div>'}</div>
            <h4>Lý do từ chối liên quan đến vùng này</h4>
            <div class="review-list compact">${reasonRowsHtml(rejectSummary)}</div>
            <div class="manual-review-box">
                <textarea id="zoneManualNote" rows="3" placeholder="Ghi chú thủ công cho vùng này">${escapeHtml(latestReview?.note || '')}</textarea>
                <div class="manual-review-actions">
                    ${manualZoneLabels().map(([label, text]) => `<button class="chart-control" type="button" data-zone-label="${escapeAttr(label)}" data-zone-id="${escapeAttr(id)}">${termLabelHtml(label, text)}</button>`).join('')}
                    <button class="chart-control" type="button" data-zone-focus="${escapeAttr(id)}">Xem trên biểu đồ</button>
                </div>
            </div>
        `;
    }

    function setupCandidates(result) {
        return result?.debug?.setupCandidates || result?.summary?.setupCandidates || result?.setupCandidates || result?.setup_candidates || [];
    }

    function manualZoneLabels() {
        return [
            ['ZONE_GOOD', translateReviewLabel('ZONE_GOOD')],
            ['ZONE_BAD', translateReviewLabel('ZONE_BAD')],
            ['ZONE_TRADEABLE', translateReviewLabel('ZONE_TRADEABLE')],
            ['ZONE_NOT_TRADEABLE', translateReviewLabel('ZONE_NOT_TRADEABLE')],
            ['BROKEN_CORRECT', translateReviewLabel('BROKEN_CORRECT')],
            ['BROKEN_WRONG', translateReviewLabel('BROKEN_WRONG')],
            ['STRENGTH_CORRECT', translateReviewLabel('STRENGTH_CORRECT')],
            ['STRENGTH_TOO_LOW', translateReviewLabel('STRENGTH_TOO_LOW')],
            ['STRENGTH_TOO_HIGH', translateReviewLabel('STRENGTH_TOO_HIGH')],
            ['ORIGIN_CORRECT', translateReviewLabel('ORIGIN_CORRECT')],
            ['ORIGIN_WRONG', translateReviewLabel('ORIGIN_WRONG')],
        ];
    }

    function strengthBreakdownHtml(breakdown) {
        const keys = ['bosScore', 'impulseScore', 'freshnessScore', 'touchPenalty', 'brokenPenalty', 'trendContextScore', 'finalScore'];
        return keys.map((key) => `<div class="review-item"><strong>${escapeHtml(formatDisplayLabel(key, 'strengthFields'))}</strong><span>${escapeHtml(breakdown[key] ?? 0)}</span></div>`).join('');
    }

    function candleText(candle) {
        if (!candle) return '';
        return `O ${candle.open ?? ''} H ${candle.high ?? ''} L ${candle.low ?? ''} C ${candle.close ?? ''}`;
    }

    function countBy(items) {
        return items.reduce((acc, key) => {
            const clean = String(key || 'UNKNOWN');
            acc[clean] = (acc[clean] || 0) + 1;
            return acc;
        }, {});
    }

    function serverSetupCandidatesHtml(result) {
        const candidates = setupCandidates(result);
        if (!candidates.length) {
            return '<div class="notice">Mẫu ứng viên chỉ được lưu khi mức thông tin kiểm tra từ 2 trở lên.</div>';
        }
        const reasons = ['ZONE_ALREADY_BROKEN', 'WEAK_H4_ZONE', 'NO_M15_CONFIRMATION', 'DIRTY_SIDEWAY', 'BAD_RR', 'LIMIT_NOT_FILLED'];
        const selectedReason = state.candidateReviewReason || 'ZONE_ALREADY_BROKEN';
        const filtered = candidates.filter((candidate) => (candidate.rejectReason || 'ACCEPT') === selectedReason);
        const selected = candidates.find((candidate) => candidate.id === state.selectedCandidateId) || filtered[0] || candidates[0];
        return `
            <h3>Rà soát mẫu ứng viên</h3>
            <div class="review-toolbar">
                ${reasons.map((reason) => `<button class="chart-control ${selectedReason === reason ? 'is-active' : ''}" type="button" data-candidate-reason="${escapeAttr(reason)}">${termLabelHtml(reason, translateReasonCode(reason))} (${number(candidates.filter((item) => item.rejectReason === reason).length)})</button>`).join('')}
            </div>
            <div class="zone-review-grid">
                <div class="backtest-trade-list candidate-review-list">
                    ${filtered.length ? filtered.slice(0, 300).map(candidateRowHtml).join('') : '<div class="notice">Không có mẫu ứng viên nào với lý do này.</div>'}
                </div>
                <div class="zone-review-detail">${selected ? candidateDetailHtml(selected) : '<div class="notice">Chưa chọn mẫu ứng viên.</div>'}</div>
            </div>
        `;
    }

    function candidateRowHtml(candidate, index) {
        const sideClass = candidate.decision === 'ACCEPT' ? 'long' : 'short';
        const resultClass = candidate.decision === 'ACCEPT' ? 'win' : 'loss';
        const active = candidate.id === state.selectedCandidateId ? ' is-active' : '';
        return `
            <button class="backtest-trade-row candidate-review-row${active}" type="button" data-candidate-id="${escapeAttr(candidate.id || '')}">
                <span class="trade-side ${sideClass}">${escapeHtml(translateDecision(candidate.decision || ''))}</span>
                <div class="trade-row-main">
                    <strong>${escapeHtml(candidate.time || '')} | ${escapeHtml(displayZone(candidate.zoneType || ''))} | RR ${escapeHtml(candidate.rr ?? '')}</strong>
                    <span>${candidate.rejectReason ? termLabelHtml(candidate.rejectReason, translateReasonCode(candidate.rejectReason)) : escapeHtml(translateDisplayText(candidate.reason || 'Được chấp nhận'))} | Vùng ${escapeHtml(candidate.h4ZoneId || '')}</span>
                </div>
                <span class="trade-r ${resultClass}">#${number(index + 1)}</span>
            </button>
        `;
    }

    function candidateDetailHtml(candidate) {
        const zoneState = candidate.zoneStateAtCandidate || {};
        return `
            <div class="memory-summary">
                ${memoryRow('Mẫu ứng viên', `${candidate.time || ''} | ${candidate.rejectReason ? translateReasonCode(candidate.rejectReason) : translateDecision(candidate.decision || '')}`)}
                ${memoryRow('Vùng giá', `${displayZone(candidate.zoneType || '')} ${candidate.zoneLow ?? ''} - ${candidate.zoneHigh ?? ''} | ${candidate.h4ZoneId || candidate.zoneId || ''}`)}
                ${memoryRow('Trạng thái vùng lúc đó', zoneLifecycleText({}, zoneState))}
                ${memoryRow('Nến phá vùng', zoneState.broken ? `${zoneState.brokenTime || candidate.brokenTime || ''} | ${translateDisplayText(candidate.brokenReason || zoneState.brokenReason || '')}` : 'Không')}
                ${memoryRow('Xác nhận M15', `Giá quay lại vùng: ${displayBoolean(!!candidate.priceReturnedToZone)} | Phản ứng: ${displayBoolean(!!candidate.m15Reaction)} | Phá cấu trúc: ${displayBoolean(!!candidate.m15StructureBreak)} | QML: ${displayBoolean(!!candidate.qmlDetected)}`)}
                ${memoryRow('RR', candidate.rr ?? '')}
                ${memoryRow('Điểm vào / SL / TP', `${candidate.proposedEntry ?? ''} / ${candidate.proposedSL ?? ''} / ${candidate.proposedTP ?? ''}`)}
                ${memoryRow('Lý do', translateDisplayText(candidate.reason || candidate.zoneRejectReason || ''))}
            </div>
            <h4>Chi tiết điểm độ mạnh</h4>
            <div class="review-list compact">${strengthBreakdownHtml(candidate.strengthBreakdown || {})}</div>
            <h4>Nến M15 quanh mẫu ứng viên</h4>
            <div class="review-list compact">${(candidate.nearbyM15Candles || []).map((bar) => `<div class="review-item"><strong>${escapeHtml(bar.time || '')}</strong><span>${escapeHtml(candleText(bar))}</span></div>`).join('') || '<div class="notice">Chưa lưu nến nào ở gần.</div>'}</div>
            <div class="manual-review-actions">
                <button class="chart-control" type="button" data-candidate-focus="${escapeAttr(candidate.id || '')}">Xem trên biểu đồ</button>
            </div>
        `;
    }

    function serverBreakdownHtml(breakdowns) {
        const topLoss = topCountLabel(breakdowns.lossReasons, displayBacktestReason);
        const topSkip = topCountLabel(breakdowns.skippedReasons, translateReasonCode);
        const bestTag = bestWinrateLabel(breakdowns.winrateByTags, displayTag);
        const bestTrend = bestWinrateLabel(breakdowns.winrateByH4Trend, displayTrend);
        return [
            backtestMetricHtml('Lý do thua nhiều nhất', topLoss || 'Chưa có'),
            backtestMetricHtml('Lý do bỏ qua nhiều nhất', topSkip || 'Chưa có'),
            backtestMetricHtml('Nhãn có kết quả tốt nhất', bestTag || 'Chưa có'),
            backtestMetricHtml('Xu hướng H4 tốt nhất', bestTrend || 'Chưa có'),
        ].join('');
    }

    function topCountLabel(items, labelFormatter = translateReasonCode) {
        const entries = Object.entries(items || {});
        if (!entries.length) return '';
        entries.sort((a, b) => Number(b[1]) - Number(a[1]));
        return `${labelFormatter(entries[0][0])} (${number(entries[0][1])})`;
    }

    function bestWinrateLabel(items, labelFormatter = formatDisplayLabel) {
        const entries = Object.entries(items || {}).filter(([, value]) => Number(value.total || 0) > 0);
        if (!entries.length) return '';
        entries.sort((a, b) => Number(b[1].winRate || 0) - Number(a[1].winRate || 0));
        return `${labelFormatter(entries[0][0])} ${formatFixed(entries[0][1].winRate || 0, 0)}%/${number(entries[0][1].total || 0)}`;
    }

    function serverTradeListHtml(trades) {
        const rows = trades.slice(0, 200);
        if (!rows.length) {
            return '<div class="notice">Không có lệnh giả lập trong kết quả này.</div>';
        }
        return rows
            .map((trade) => `
                <div class="backtest-trade-row">
                    <span class="trade-side ${String(trade.direction || '').toLowerCase() === 'buy' ? 'long' : 'short'}">${escapeHtml(displayDirection(trade.direction || ''))}</span>
                    <div class="trade-row-main">
                        <strong>${escapeHtml(translateDisplayText(trade.result || ''))} | ${escapeHtml(trade.entryTime || '')}</strong>
                        <span>Điểm vào ${escapeHtml(trade.entryPrice ?? '')} → Điểm thoát ${escapeHtml(trade.exitPrice ?? '')} | ${escapeHtml(translateDisplayText(trade.reasonEnter || trade.reasonSkipped || ''))}</span>
                    </div>
                    <span class="trade-r ${Number(trade.rrResult || 0) >= 0 ? 'win' : 'loss'}">${formatSigned(trade.rrResult || 0, 2)}R</span>
                </div>
            `)
            .join('');
    }

    function decorateZoneReview(result) {
        if (!result) return;
        $$('[data-zone-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                state.h4ZoneReviewFilter = button.dataset.zoneFilter || 'active';
                state.selectedH4ZoneId = '';
                renderBacktestLab();
            });
        });
        $$('.zone-review-row[data-zone-id]').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedH4ZoneId = button.dataset.zoneId || '';
                renderBacktestLab();
            });
        });
        $$('[data-zone-focus]').forEach((button) => {
            button.addEventListener('click', () => focusDebugZone(button.dataset.zoneFocus).catch((e) => showToast(e.message)));
        });
        $$('[data-zone-label]').forEach((button) => {
            button.addEventListener('click', () => saveZoneManualReview(button.dataset.zoneId, button.dataset.zoneLabel).catch((e) => showToast(e.message)));
        });
    }

    function decorateCandidateReview(result) {
        if (!result) return;
        $$('[data-candidate-reason]').forEach((button) => {
            button.addEventListener('click', () => {
                state.candidateReviewReason = button.dataset.candidateReason || 'ZONE_ALREADY_BROKEN';
                state.selectedCandidateId = '';
                renderBacktestLab();
            });
        });
        $$('[data-candidate-id]').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedCandidateId = button.dataset.candidateId || '';
                renderBacktestLab();
            });
        });
        $$('[data-candidate-focus]').forEach((button) => {
            button.addEventListener('click', () => focusDebugCandidate(button.dataset.candidateFocus).catch((e) => showToast(e.message)));
        });
    }

    function decorateCalibrationReview() {
        $$('.calibration-queue-row[data-calibration-index]').forEach((button) => {
            button.addEventListener('click', () => {
                state.calibrationIndex = clamp(Number(button.dataset.calibrationIndex || 0), 0, Math.max(0, state.calibrationQueue.length - 1));
                renderCalibrationReview();
            });
        });
        $$('[data-calibration-label]').forEach((button) => {
            button.addEventListener('click', () => saveCalibrationManualReview(button.dataset.calibrationLabel).catch((e) => showToast(e.message)));
        });
        $('#calibrationFocusChartBtn')?.addEventListener('click', () => focusCalibrationItem().catch((e) => showToast(e.message)));
    }

    async function loadCalibrationQueue() {
        const versionId = $('#calibrationVersionSelect').value || state.selectedStrategyVersionId;
        const payload = {
            strategyVersionId: versionId,
            symbol: $('#calibrationSymbolSelect').value,
            dataSplit: $('#calibrationSplitSelect').value || 'train',
            preset: $('#calibrationPresetSelect').value || 'loose-debug',
            queueType: $('#calibrationQueueTypeSelect').value || 'ZONE_ALREADY_BROKEN',
            limit: intNum($('#calibrationLimitInput').value || 50),
            debugLevel: 3,
        };
        const data = await api('calibration_queue', payload);
        state.calibrationQueue = data.items || [];
        state.calibrationQueueType = data.queueType || payload.queueType;
        state.calibrationIndex = 0;
        state.calibrationRun = data.backtest || null;
        state.lastServerBacktest = data.backtest || state.lastServerBacktest;
        state.manualReviewLabels = data.manualReviewLabels || state.manualReviewLabels;
        state.chartView.activeRunId = data.backtest?.backtestRunId || data.backtest?.id || state.chartView.activeRunId;
        showToast(`Đã tải ${state.calibrationQueue.length} mục hiệu chỉnh`);
        renderAll();
    }

    async function saveCalibrationManualReview(label) {
        const item = state.calibrationQueue[state.calibrationIndex];
        if (!item) return;
        const data = await api('save_manual_review', {
            reviewType: item.reviewType,
            targetId: item.targetId,
            symbol: item.symbol,
            timeframe: item.timeframe,
            label,
            note: $('#calibrationManualNote')?.value || '',
            relatedBacktestRunId: item.relatedBacktestRunId || state.calibrationRun?.summary?.backtestRunId || '',
            relatedStrategyVersionId: item.relatedStrategyVersionId || state.selectedStrategyVersionId || '',
            snapshotData: item.snapshotData || {},
        });
        state.manualReviews = data.manual_reviews || state.manualReviews;
        state.zoneManualReviews = data.zone_manual_reviews || state.zoneManualReviews;
        item.latestReview = data.review;
        item.reviewed = true;
        if (state.calibrationIndex < state.calibrationQueue.length - 1) {
            state.calibrationIndex += 1;
        }
        showToast(`Đã lưu: ${translateReviewLabel(label)}`);
        renderCalibrationReview();
    }

    async function focusCalibrationItem() {
        const item = state.calibrationQueue[state.calibrationIndex];
        if (!item) return;
        state.lastServerBacktest = state.calibrationRun || state.lastServerBacktest;
        const snapshot = item.snapshotData || {};
        const zone = snapshot.zone || snapshot.candidate?.zone || {};
        const candidate = snapshot.candidate || {};
        const trade = snapshot.trade || {};
        const symbol = item.symbol || candidate.symbol || trade.symbol || zone.symbol || '';
        const timeframe = item.reviewType === 'zone' ? (item.timeframe || 'H4') : 'M15';
        const file = findMarketFile(symbol, timeframe);
        if (!file) {
            showToast(`${symbol} ${timeframe} chưa có tệp dữ liệu thị trường.`);
            return;
        }
        await loadMarketData(file.file);
        state.chartView.highlightedZoneId = zone.id || zone.zoneId || candidate.zoneId || candidate.h4ZoneId || trade.zoneId || '';
        state.chartView.highlightedCandidateId = candidate.id || '';
        state.chartView.selectedTradeId = trade.id || null;
        state.chartView.overlays.zones = true;
        const time = item.reviewType === 'zone'
            ? (zone.originCandleTime || zone.createdAt || item.time)
            : (candidate.time || trade.entryTime || item.time);
        focusChartTime(timeToSeconds(time));
        showToast('Đã mở mục hiệu chỉnh trên biểu đồ');
    }

    async function loadCalibrationReport() {
        const data = await api('calibration_report', {});
        state.calibrationReport = data.report || null;
        state.manualReviews = data.manual_reviews || state.manualReviews;
        renderCalibrationReview();
        showToast('Đã tạo báo cáo hiệu chỉnh');
    }

    async function loadCalibrationAdvisor() {
        const data = await api('calibration_recommendations', {
            strategyVersionId: $('#calibrationVersionSelect').value || state.selectedStrategyVersionId,
            basePreset: 'balanced',
        });
        state.calibrationReport = data.report || state.calibrationReport;
        state.calibrationAdvisor = data.advisor || null;
        renderCalibrationReview();
        showToast('Đã cập nhật gợi ý hiệu chỉnh');
    }

    async function generateCalibratedPreset() {
        const data = await api('generate_calibrated_preset', {
            strategyVersionId: $('#calibrationVersionSelect').value || state.selectedStrategyVersionId,
            basePreset: 'balanced',
        });
        state.calibrationAdvisor = data.advisor || state.calibrationAdvisor;
        state.calibratedPresets = data.calibrated_presets || state.calibratedPresets;
        showToast(`Đã tạo ${translatePresetName(data.preset?.name || 'bộ thiết lập đã hiệu chỉnh')} — đang chờ phê duyệt`);
        renderAll();
    }

    async function saveZoneManualReview(zoneId, label) {
        const zone = allDebugZones(state.lastServerBacktest).find((item) => (item.id || item.zoneId || '') === zoneId);
        if (!zone) return;
        const data = await api('save_zone_manual_review', {
            zoneId,
            symbol: zone.symbol || state.lastServerBacktest?.summary?.symbol || state.lastServerBacktest?.symbol || '',
            timeframe: zone.timeframe || 'H4',
            label,
            note: $('#zoneManualNote')?.value || '',
            relatedBacktestRunId: state.lastServerBacktest?.summary?.backtestRunId || state.lastServerBacktest?.backtestRunId || '',
            relatedStrategyVersionId: state.lastServerBacktest?.summary?.strategyVersionId || state.selectedStrategyVersionId || '',
            snapshotData: { zone },
        });
        state.manualReviews = data.manual_reviews || state.manualReviews;
        state.zoneManualReviews = data.zone_manual_reviews || state.zoneManualReviews;
        showToast('Đã lưu nhãn thủ công cho vùng giá');
        renderBacktestLab();
    }

    async function focusDebugZone(zoneId) {
        const zone = allDebugZones(state.lastServerBacktest).find((item) => (item.id || item.zoneId || '') === zoneId);
        if (!zone) return;
        const symbol = zone.symbol || state.lastServerBacktest?.summary?.symbol || state.lastServerBacktest?.symbol || '';
        const timeframe = zone.timeframe || 'H4';
        const file = findMarketFile(symbol, timeframe);
        if (!file) {
            showToast(`${symbol} ${timeframe} chưa có tệp dữ liệu thị trường.`);
            return;
        }
        await loadMarketData(file.file);
        state.chartView.highlightedZoneId = zoneId;
        state.chartView.overlays.zones = true;
        focusChartTime(timeToSeconds(zone.originCandleTime || zone.createdAt));
        showToast('Đã mở vùng H4 trên biểu đồ');
    }

    async function focusDebugCandidate(candidateId) {
        const candidate = setupCandidates(state.lastServerBacktest).find((item) => item.id === candidateId);
        if (!candidate) return;
        const symbol = candidate.symbol || state.lastServerBacktest?.summary?.symbol || state.lastServerBacktest?.symbol || '';
        const file = findMarketFile(symbol, 'M15');
        if (!file) {
            showToast(`${symbol} M15 chưa có tệp dữ liệu thị trường.`);
            return;
        }
        await loadMarketData(file.file);
        state.chartView.highlightedCandidateId = candidateId;
        state.chartView.highlightedZoneId = candidate.zoneId || candidate.h4ZoneId || '';
        state.chartView.overlays.zones = true;
        focusChartTime(timeToSeconds(candidate.time || candidate.timestamp));
        showToast('Đã mở mẫu bị từ chối trên biểu đồ M15');
    }

    function decorateServerTradeRows(trades) {
        $$('#serverBacktestTrades .backtest-trade-row').forEach((row, index) => {
            const trade = trades[index] || {};
            row.setAttribute('role', 'button');
            row.setAttribute('tabindex', '0');
            const toggle = () => {
                const existing = row.querySelector('.trade-debug-detail');
                if (existing) {
                    existing.remove();
                    return;
                }
                const detail = document.createElement('div');
                detail.className = 'memory-summary trade-debug-detail';
                detail.innerHTML = [
                    memoryRow('Lý do vào lệnh', translateDisplayText(trade.reasonEnter || trade.reasonSkipped || '')),
                    memoryRow('Lý do thoát lệnh', translateDisplayText(trade.reasonExit || '')),
                    memoryRow('Vùng giá', `${displayZone(trade.zoneType || '')} ${trade.zoneId || ''} | Độ mạnh ${trade.zoneStrength ?? ''}`),
                    memoryRow('M15 / Tỷ lệ RR', `${displaySetup(trade.setupType || '')} | ${trade.rrResult ?? ''}R`),
                    memoryRow('Điểm vào / SL / TP', `${trade.entryPrice ?? ''} / ${trade.stopLoss ?? ''} / ${trade.takeProfit ?? ''}`),
                    memoryRow('Nhãn', (trade.tags || []).map((tag) => displayTag(tag)).join(', ')),
                ].join('');
                row.appendChild(detail);
            };
            row.addEventListener('click', toggle);
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });
        });
    }

    function evaluationHtml(evaluation) {
        const fail = evaluation.failReasons || [];
        return `
            <h3>Kết quả đánh giá</h3>
            <div class="memory-summary">
                ${memoryRow('Kết quả', evaluation.passed ? 'Đạt' : 'Không đạt')}
                ${memoryRow('Điểm', `${evaluation.scoreParent} → ${evaluation.scoreNew}`)}
                ${memoryRow('Lý do không đạt', fail.length ? fail.map(translateDisplayText).join('; ') : 'Không có')}
            </div>
            <div class="comparison-grid">
                ${comparisonBlock('Dữ liệu huấn luyện', evaluation.trainComparison)}
                ${comparisonBlock('Dữ liệu xác thực', evaluation.validationComparison)}
                ${comparisonBlock('Dữ liệu kiểm tra cuối', evaluation.finalTestComparison)}
            </div>
        `;
    }

    function comparisonBlock(label, comparison) {
        const parent = comparison?.parent || {};
        const newer = comparison?.new || {};
        return `
            <div class="comparison-card">
                <strong>${escapeHtml(label)}</strong>
                <span>Bản gốc: ${formatSigned(parent.netProfitPercent || 0, 2)}%, sụt giảm ${formatFixed(parent.maxDrawdownPercent || 0, 2)}%, hệ số lợi nhuận ${formatFixed(parent.profitFactor || 0, 2)}, ${number(parent.totalTrades || 0)} lệnh</span>
                <span>Bản mới: ${formatSigned(newer.netProfitPercent || 0, 2)}%, sụt giảm ${formatFixed(newer.maxDrawdownPercent || 0, 2)}%, hệ số lợi nhuận ${formatFixed(newer.profitFactor || 0, 2)}, ${number(newer.totalTrades || 0)} lệnh</span>
            </div>
        `;
    }

    function diffObjects(oldValue, newValue, prefix = '') {
        const keys = [...new Set([...Object.keys(oldValue || {}), ...Object.keys(newValue || {})])].sort();
        const changes = [];
        keys.forEach((key) => {
            const path = prefix ? `${prefix}.${key}` : key;
            const before = oldValue ? oldValue[key] : undefined;
            const after = newValue ? newValue[key] : undefined;
            const bothObjects = before && after && typeof before === 'object' && typeof after === 'object' && !Array.isArray(before) && !Array.isArray(after);
            if (bothObjects) {
                changes.push(...diffObjects(before, after, path));
            } else if (JSON.stringify(before) !== JSON.stringify(after)) {
                changes.push({ field: path, oldValue: before, newValue: after });
            }
        });
        return changes;
    }

    function managementSummaryHtml(strategy) {
        const m = strategy.management || {};
        const rows = [
            [
                'Dời SL về điểm vào',
                m.be_enabled === false
                    ? 'Tắt'
                    : `Bật khi lệnh đạt khoảng ${formatNumberValue(m.be_trigger_r_min ?? 1.8)}R đến ${formatNumberValue(m.be_trigger_r_max ?? 2)}R`,
            ],
            [
                'Chốt từng phần',
                m.partial_close ? `Có partial, khối lượng thoát mặc định ${formatNumberValue(m.close_size_pct ?? 100)}%` : 'Không partial, khi thoát thì đóng toàn bộ',
            ],
            [
                'Thoát khi sai kỳ vọng',
                m.exit_on_m15_opposite_structure === false
                    ? 'Tắt'
                    : 'Bật: nếu M15 tạo cấu trúc ngược hướng lệnh thì chờ hồi về key vừa phá để đóng',
            ],
            [
                'M15 đi ngang rồi phá ngược',
                m.exit_on_m15_sideway_break_against_trade === false
                    ? 'Tắt'
                    : 'Bật: nếu M15 đi ngang rồi phá ngược qua vùng vào lệnh/kỳ vọng thì chờ hồi để thoát',
            ],
            [
                'Phản ứng tại vùng TP H4',
                m.exit_at_h4_tp_zone_on_m15_reversal === false
                    ? 'Tắt'
                    : 'Bật: nếu tới vùng cản H4/TP mà M15 có phản ứng ngược mạnh thì đóng theo nhịp hồi',
            ],
            [
                'Vùng chờ hồi để thoát',
                displayExitRetestLevel(m.exit_retest_level || 'broken_m15_key_level'),
            ],
        ];

        return rows
            .map(([label, value]) => {
                return `
                    <div class="management-item">
                        <strong>${escapeHtml(label)}</strong>
                        <span>${escapeHtml(value)}</span>
                    </div>
                `;
            })
            .join('');
    }

    async function scoreActiveCase() {
        const edited = readEditedCase();
        const data = await api('score_case', { case: edited });
        state.activeScore = data.score;
        $('#decisionSummary').innerHTML = decisionHtml(state.activeScore);
        drawCaseChart(edited);
    }

    async function reviewActiveCase(decision) {
        const edited = readEditedCase();
        const original = activeCase();
        const corrections = {};
        Object.keys(edited).forEach((key) => {
            if (JSON.stringify(edited[key]) !== JSON.stringify(original[key])) {
                corrections[key] = { from: original[key], to: edited[key] };
            }
        });
        const data = await api('review_case', {
            case: edited,
            ai_score: state.activeScore,
            your_decision: decision,
            your_note: $('#reviewNote').value.trim(),
            corrections,
        });
        state.stats = data.stats;
        state.recentReviews = data.recent_reviews;
        if (decision === 'edited') {
            state.candidateCases[state.activeCaseIndex] = edited;
            await api('save_candidate_cases', { candidate_cases: state.candidateCases });
        }
        showToast('Đã lưu phản hồi');
        nextCase();
        renderAll();
    }

    function drawCaseChart(c) {
        const canvas = $('#caseChart');
        const ctx = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.max(640, Math.floor(rect.width * dpr));
        canvas.height = Math.floor(460 * dpr);
        ctx.scale(dpr, dpr);

        const width = canvas.width / dpr;
        const height = canvas.height / dpr;
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = bodyIsDark() ? '#0f1720' : '#f8fafc';
        ctx.fillRect(0, 0, width, height);

        const candles = buildCandles(c);
        const prices = candles.flatMap((bar) => [bar.high, bar.low]);
        const zones = c.chart?.zones || [];
        zones.forEach((z) => prices.push(z.from, z.to));
        ['entry', 'sl', 'selected_tp'].forEach((key) => {
            if (Number.isFinite(Number(c[key]))) prices.push(Number(c[key]));
        });
        const min = Math.min(...prices);
        const max = Math.max(...prices);
        const pad = (max - min) * 0.15 || 1;
        const lo = min - pad;
        const hi = max + pad;
        const plot = { left: 56, right: 18, top: 22, bottom: 42 };
        const plotW = width - plot.left - plot.right;
        const plotH = height - plot.top - plot.bottom;
        const y = (price) => plot.top + ((hi - price) / (hi - lo)) * plotH;
        const x = (i) => plot.left + (i / Math.max(1, candles.length - 1)) * plotW;

        ctx.strokeStyle = bodyIsDark() ? '#2e3b49' : '#d8dde3';
        ctx.lineWidth = 1;
        for (let i = 0; i < 6; i++) {
            const yy = plot.top + (i / 5) * plotH;
            ctx.beginPath();
            ctx.moveTo(plot.left, yy);
            ctx.lineTo(width - plot.right, yy);
            ctx.stroke();
            const price = hi - (i / 5) * (hi - lo);
            ctx.fillStyle = '#65717f';
            ctx.font = '12px Segoe UI, Arial';
            ctx.fillText(formatPrice(price, c.symbol), 8, yy + 4);
        }

        zones.forEach((zone) => {
            const top = y(Math.max(zone.from, zone.to));
            const bottom = y(Math.min(zone.from, zone.to));
            ctx.fillStyle = zone.type === 'supply' ? 'rgba(188, 61, 61, 0.14)' : 'rgba(22, 132, 95, 0.14)';
            ctx.fillRect(plot.left, top, plotW, bottom - top);
            ctx.fillStyle = zone.type === 'supply' ? '#bc3d3d' : '#16845f';
            ctx.fillText(displayZone(zone.type), plot.left + 8, top + 16);
        });

        const candleW = Math.max(4, plotW / candles.length * 0.55);
        candles.forEach((bar, i) => {
            const xx = x(i);
            const up = bar.close >= bar.open;
            ctx.strokeStyle = up ? '#16845f' : '#bc3d3d';
            ctx.fillStyle = up ? '#16845f' : '#bc3d3d';
            ctx.beginPath();
            ctx.moveTo(xx, y(bar.high));
            ctx.lineTo(xx, y(bar.low));
            ctx.stroke();
            const bodyTop = y(Math.max(bar.open, bar.close));
            const bodyBottom = y(Math.min(bar.open, bar.close));
            ctx.fillRect(xx - candleW / 2, bodyTop, candleW, Math.max(2, bodyBottom - bodyTop));
        });

        drawLevel(ctx, y, plot, width, c.entry, '#1f6feb', 'LỆNH', c.symbol);
        drawLevel(ctx, y, plot, width, c.sl, '#bc3d3d', 'SL', c.symbol);
        drawLevel(ctx, y, plot, width, c.selected_tp, '#16845f', 'TP', c.symbol);

        ctx.fillStyle = bodyIsDark() ? '#a8b3c1' : '#17202a';
        ctx.font = '600 13px Segoe UI, Arial';
        ctx.fillText(`${c.symbol} ${displayDirection(c.direction)} ${displaySetup(c.setup_type)}`, plot.left, height - 16);
    }

    function scheduleChartInteractionDraw(data) {
        chartInteractionData = data;
        if (chartInteractionFrame) return;
        chartInteractionFrame = requestAnimationFrame(() => {
            chartInteractionFrame = 0;
            const nextData = chartInteractionData;
            chartInteractionData = null;
            if (nextData && nextData === state.activeMarketData) drawMarketChart(nextData, { interactionFrame: true });
        });
    }

    function scheduleChartCrosshairDraw() {
        if (chartCrosshairFrame) return;
        chartCrosshairFrame = requestAnimationFrame(() => {
            chartCrosshairFrame = 0;
            drawMarketChartCrosshairOverlay();
        });
    }

    function drawMarketChart(data, options = {}) {
        if (chartInteractionFrame) {
            cancelAnimationFrame(chartInteractionFrame);
            chartInteractionFrame = 0;
            chartInteractionData = null;
        }
        const canvas = $('#marketChart');
        const ctx = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        if (!rect.width || !rect.height) return;
        const pixelWidth = Math.max(1, Math.floor(rect.width * dpr));
        const pixelHeight = Math.max(1, Math.floor(rect.height * dpr));
        if (canvas.width !== pixelWidth) canvas.width = pixelWidth;
        if (canvas.height !== pixelHeight) canvas.height = pixelHeight;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const width = canvas.width / dpr;
        const height = canvas.height / dpr;
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = bodyIsDark() ? '#0f1720' : '#f8fafc';
        ctx.fillRect(0, 0, width, height);

        const allBars = data?.bars || [];
        if (!options.interactionFrame) {
            $('#marketChartTitle').textContent = data ? data.symbol : 'Biểu đồ dữ liệu MT5';
            $('#marketChartMeta').textContent = data ? `${data.timeframe} · ${number(allBars.length)} nến` : 'Chưa chọn tệp';
        }
        if (!allBars.length) {
            state.chartView.lastLayout = null;
            drawPaTrainingLevelOverlay(null);
            drawMarketChartCrosshairOverlay(null, data);
            ctx.fillStyle = '#65717f';
            ctx.fillText('Không có dữ liệu nến để vẽ.', 24, 40);
            return;
        }

        const layout = buildMarketChartLayout(data, width, height);
        const analytics = getChartAnalytics(data);
        layout.analytics = analytics;
        state.chartView.lastLayout = layout;
        if (state.activeTab === 'pa-training') {
            state.chartView.selectedTradeId = null;
        } else {
            ensureSelectedTrade(analytics);
        }
        if (!options.interactionFrame) {
            renderBacktestPanels(analytics);
            renderReplayControls(data);
        }

        drawChartGrid(ctx, layout, data.symbol);
        drawDataWarnings(ctx, layout, analytics);
        drawMarketPrice(ctx, layout);
        drawMarketIndicators(ctx, layout, analytics);
        drawCompareSeries(ctx, layout);
        drawStrategyDebugOverlay(ctx, layout, analytics);
        drawBacktestTrades(ctx, layout, analytics);
        drawPaNearbyZones(ctx, layout);
        drawMarketDrawings(ctx, layout, data);
        drawMarketAxis(ctx, layout, data.symbol);
        drawMarketFooter(ctx, layout, data);
        drawPaTrainingLevelOverlay(layout);
        drawMarketChartCrosshairOverlay(layout, data);
        if (!options.interactionFrame) {
            updatePaTrainingTimeframeNotice(data);
            renderFloatingToolbar();
            renderFavoriteToolbar();
        }
    }

    function buildMarketChartLayout(data, width, height) {
        const allBars = data.bars || [];
        const minBars = Math.min(12, allBars.length);
        const maxBars = Math.max(minBars, allBars.length);
        state.chartView.barsOnScreen = clamp(Math.round(state.chartView.barsOnScreen || 180), minBars, maxBars);
        const offsetLimits = chartRightOffsetLimits(allBars.length, state.chartView.barsOnScreen);
        state.chartView.rightOffset = clamp(Math.round(state.chartView.rightOffset || 0), offsetLimits.min, offsetLimits.max);
        state.chartView.priceScale = clamp(Number(state.chartView.priceScale || 1), 0.2, 20);
        state.chartView.priceOffset = Number(state.chartView.priceOffset || 0);

        if (state.chartView.replay.enabled && state.chartView.replay.time !== null && Number.isFinite(Number(state.chartView.replay.time))) {
            state.chartView.replay.index = chartIndexAtOrBeforeTime(allBars, Number(state.chartView.replay.time), state.chartView.replay.index);
        } else if (state.chartView.replay.enabled) {
            state.chartView.replay.index = clamp(Math.round(state.chartView.replay.index || 0), 0, Math.max(0, allBars.length - 1));
        }

        const visibleCount = Math.min(state.chartView.barsOnScreen, allBars.length);
        const virtualLastExclusive = Math.round(allBars.length - state.chartView.rightOffset);
        const first = Math.round(virtualLastExclusive - visibleCount);
        const lastExclusive = first + visibleCount;
        const barsFirstIndex = clamp(first, 0, allBars.length);
        const barsLastExclusive = clamp(lastExclusive, 0, allBars.length);
        const bars = barsLastExclusive > barsFirstIndex ? allBars.slice(barsFirstIndex, barsLastExclusive) : [];
        const fallbackPriceBars = allBars.slice(Math.max(0, allBars.length - Math.max(1, minBars)), allBars.length);
        const priceBars = bars.length ? bars : fallbackPriceBars;

        const plot = {
            left: 12,
            right: 72,
            top: 26,
            bottom: 42,
            priceAxisWidth: 64,
        };
        const plotW = width - plot.left - plot.right;
        const plotH = height - plot.top - plot.bottom;
        const priceArea = {
            left: plot.left,
            right: plot.left + plotW,
            top: plot.top,
            bottom: plot.top + plotH,
            width: plotW,
            height: plotH,
        };
        const panelBottom = priceArea.bottom;
        const axisArea = {
            left: priceArea.right,
            right: width,
            top: plot.top,
            bottom: panelBottom,
            width: width - priceArea.right,
        };

        const prices = priceBars.flatMap((bar) => [Number(bar.high), Number(bar.low)]).filter(Number.isFinite);
        const teachingCase = state.activeTab === 'pa-training' ? paTrainingSelectedTeachingCase() : null;
        if (teachingCase && paTrainingCaseFitsChartData(teachingCase, data)) {
            [teachingCase.entryPrice, teachingCase.initialStop, teachingCase.targetPrice, teachingCase.targetPrice2, teachingCase.zoneLow, teachingCase.zoneHigh]
                .filter(value => value !== null && value !== undefined)
                .map(Number)
                .filter(Number.isFinite)
                .forEach((price) => prices.push(price));
        }
        if (state.activeTab === 'pa-training' && paNearbyZonesVisible) {
            const nearby = paNearbyZones(data);
            [...nearby.entries, ...nearby.targets].forEach(zone => prices.push(zone.low, zone.high));
        }
        if (!prices.length) prices.push(1);
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);
        const scaleMode = state.chartView.scaleMode || 'auto';
        const scaleBase = Number(priceBars[0]?.close) || minPrice || 1;
        const toScaleValue = (price) => {
            const value = Number(price);
            if (scaleMode === 'log') return Math.log(Math.max(0.0000001, value));
            if (scaleMode === 'percent') return scaleBase ? ((value - scaleBase) / scaleBase) * 100 : 0;
            return value;
        };
        const fromScaleValue = (value) => {
            if (scaleMode === 'log') return Math.exp(value);
            if (scaleMode === 'percent') return scaleBase * (1 + value / 100);
            return value;
        };
        const scaledPrices = prices.map(toScaleValue).filter(Number.isFinite);
        const minScale = Math.min(...scaledPrices);
        const maxScale = Math.max(...scaledPrices);
        const mid = (minScale + maxScale) / 2 + state.chartView.priceOffset;
        const autoRange = (maxScale - minScale || Math.max(0.0001, Math.abs(maxScale) * 0.01)) * 1.18;
        const range = autoRange / state.chartView.priceScale;
        const scaleLo = mid - range / 2;
        const scaleHi = mid + range / 2;
        const lo = fromScaleValue(scaleLo);
        const hi = fromScaleValue(scaleHi);
        const barStep = priceArea.width / Math.max(1, visibleCount - 1);

        const panPixelOffset = Number(state.chartView.panPixelOffset) || 0;
        const x = (barIndex) => priceArea.left + (barIndex - first) * barStep + panPixelOffset;
        const y = (price) => priceArea.top + ((scaleHi - toScaleValue(price)) / Math.max(0.0000001, scaleHi - scaleLo)) * priceArea.height;
        const barIndexAtX = (px) => first + (px - priceArea.left - panPixelOffset) / Math.max(1, barStep);
        const priceAtY = (py) => fromScaleValue(scaleHi - ((py - priceArea.top) / Math.max(1, priceArea.height)) * (scaleHi - scaleLo));

        return {
            width,
            height,
            data,
            allBars,
            bars,
            first,
            lastExclusive,
            barsFirstIndex,
            barsLastExclusive,
            visibleCount,
            plot,
            priceArea,
            panelBottom,
            axisArea,
            lo,
            hi,
            scaleLo,
            scaleHi,
            scaleMode,
            scaleBase,
            fromScaleValue,
            toScaleValue,
            x,
            y,
            barIndexAtX,
            priceAtY,
            barStep,
            panPixelOffset,
        };
    }

    function drawChartGrid(ctx, layout, symbol) {
        const { priceArea, panelBottom } = layout;
        ctx.strokeStyle = bodyIsDark() ? '#243140' : '#e7eaee';
        ctx.lineWidth = 1;
        ctx.font = '12px Segoe UI, Arial';
        for (let i = 0; i <= 8; i++) {
            const yy = priceArea.top + (i / 8) * priceArea.height;
            ctx.beginPath();
            ctx.moveTo(priceArea.left, yy);
            ctx.lineTo(priceArea.right, yy);
            ctx.stroke();
        }
        for (let i = 0; i <= 10; i++) {
            const xx = priceArea.left + (i / 10) * priceArea.width;
            ctx.beginPath();
            ctx.moveTo(xx, priceArea.top);
            ctx.lineTo(xx, panelBottom);
            ctx.stroke();
        }
    }

    function drawMarketPrice(ctx, layout) {
        const type = state.chartView.chartType || 'candles';
        if (type === 'line') {
            drawMarketLine(ctx, layout, false);
            return;
        }
        if (type === 'area') {
            drawMarketLine(ctx, layout, true);
            return;
        }
        if (type === 'bars') {
            drawMarketBars(ctx, layout);
            return;
        }
        if (type === 'heikin') {
            drawMarketCandles(ctx, layout, heikinAshiBars(layout.bars));
            return;
        }
        if (type === 'renko') {
            drawRenkoChart(ctx, layout);
            return;
        }
        drawMarketCandles(ctx, layout);
    }

    function drawMarketLine(ctx, layout, fillArea = false) {
        const { bars, barsFirstIndex, x, y, priceArea } = layout;
        const cutoff = replayCutoffIndex(layout.data);
        ctx.save();
        ctx.beginPath();
        let started = false;
        bars.forEach((bar, i) => {
            const index = barsFirstIndex + i;
            if (index > cutoff) return;
            const close = Number(bar.close);
            if (!Number.isFinite(close)) {
                started = false;
                return;
            }
            const xx = x(index);
            const yy = y(close);
            if (!started) {
                ctx.moveTo(xx, yy);
                started = true;
            } else {
                ctx.lineTo(xx, yy);
            }
        });
        if (fillArea && started) {
            const lastVisible = Math.min(layout.barsLastExclusive - 1, cutoff);
            const firstVisible = Math.max(layout.barsFirstIndex, Math.ceil(layout.barsFirstIndex));
            ctx.lineTo(layout.x(lastVisible), priceArea.bottom);
            ctx.lineTo(layout.x(firstVisible), priceArea.bottom);
            ctx.closePath();
            const gradient = ctx.createLinearGradient(0, priceArea.top, 0, priceArea.bottom);
            gradient.addColorStop(0, 'rgba(31,111,235,0.22)');
            gradient.addColorStop(1, 'rgba(31,111,235,0.02)');
            ctx.fillStyle = gradient;
            ctx.fill();
        }
        ctx.strokeStyle = '#1f6feb';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();
    }

    function drawMarketBars(ctx, layout) {
        const { bars, barsFirstIndex, x, y, barStep } = layout;
        if (shouldDrawAggregatedCandles(layout, bars)) {
            drawAggregatedCandles(ctx, layout, bars);
            return;
        }
        const half = clamp(barStep * 0.28, 3, 9);
        const cutoff = replayCutoffIndex(layout.data);
        ctx.save();
        bars.forEach((bar, i) => {
            const index = barsFirstIndex + i;
            if (index > cutoff) return;
            const open = Number(bar.open);
            const high = Number(bar.high);
            const low = Number(bar.low);
            const close = Number(bar.close);
            if (![open, high, low, close].every(Number.isFinite)) return;
            const xx = x(index);
            const up = close >= open;
            ctx.strokeStyle = up ? '#089981' : '#f23645';
            ctx.lineWidth = 1.4;
            ctx.beginPath();
            ctx.moveTo(xx, y(high));
            ctx.lineTo(xx, y(low));
            ctx.moveTo(xx - half, y(open));
            ctx.lineTo(xx, y(open));
            ctx.moveTo(xx, y(close));
            ctx.lineTo(xx + half, y(close));
            ctx.stroke();
        });
        ctx.restore();
    }

    function shouldDrawAggregatedCandles(layout, bars) {
        return layout.barStep < 0.75 && (bars?.length || 0) > layout.priceArea.width * 1.35;
    }

    function drawAggregatedCandles(ctx, layout, bars) {
        const cutoff = replayCutoffIndex(layout.data);
        const pixelStep = 2;
        const buckets = [];
        let current = null;
        const pushCurrent = () => {
            if (current) buckets.push(current);
            current = null;
        };
        bars.forEach((bar, i) => {
            const index = layout.barsFirstIndex + i;
            if (index > cutoff) return;
            const open = Number(bar.open);
            const high = Number(bar.high);
            const low = Number(bar.low);
            const close = Number(bar.close);
            if (![open, high, low, close].every(Number.isFinite)) return;
            const xx = layout.x(index);
            if (xx < layout.priceArea.left - pixelStep || xx > layout.priceArea.right + pixelStep) return;
            const bucketId = Math.floor((xx - layout.priceArea.left) / pixelStep);
            if (!current || current.bucketId !== bucketId) {
                pushCurrent();
                current = { bucketId, open, high, low, close, firstIndex: index, lastIndex: index };
                return;
            }
            current.high = Math.max(current.high, high);
            current.low = Math.min(current.low, low);
            current.close = close;
            current.lastIndex = index;
        });
        pushCurrent();
        if (!buckets.length) return;
        ctx.save();
        ctx.lineWidth = 1;
        const bodyW = 2;
        buckets.forEach((bucket) => {
            const xx = clamp(layout.priceArea.left + bucket.bucketId * pixelStep + pixelStep / 2, layout.priceArea.left, layout.priceArea.right);
            const up = bucket.close >= bucket.open;
            ctx.strokeStyle = up ? '#089981' : '#f23645';
            ctx.fillStyle = up ? '#089981' : '#f23645';
            ctx.beginPath();
            ctx.moveTo(xx, layout.y(bucket.high));
            ctx.lineTo(xx, layout.y(bucket.low));
            ctx.stroke();
            const bodyTop = layout.y(Math.max(bucket.open, bucket.close));
            const bodyBottom = layout.y(Math.min(bucket.open, bucket.close));
            ctx.fillRect(xx - bodyW / 2, bodyTop, bodyW, Math.max(1, bodyBottom - bodyTop));
        });
        ctx.restore();
    }

    function drawMarketCandles(ctx, layout, customBars = null) {
        const { barsFirstIndex, x, y, barStep } = layout;
        const bars = customBars || layout.bars;
        if (shouldDrawAggregatedCandles(layout, bars)) {
            drawAggregatedCandles(ctx, layout, bars);
            return;
        }
        const candleW = clamp(barStep * 0.58, 2, 14);
        const cutoff = replayCutoffIndex(layout.data);
        bars.forEach((bar, i) => {
            const index = barsFirstIndex + i;
            if (index > cutoff) return;
            const open = Number(bar.open);
            const high = Number(bar.high);
            const low = Number(bar.low);
            const close = Number(bar.close);
            const xx = x(index);
            const up = close >= open;
            ctx.strokeStyle = up ? '#089981' : '#f23645';
            ctx.fillStyle = up ? '#089981' : '#f23645';
            ctx.beginPath();
            ctx.moveTo(xx, y(high));
            ctx.lineTo(xx, y(low));
            ctx.stroke();
            const bodyTop = y(Math.max(open, close));
            const bodyBottom = y(Math.min(open, close));
            ctx.fillRect(xx - candleW / 2, bodyTop, candleW, Math.max(1, bodyBottom - bodyTop));
        });
    }

    function heikinAshiBars(bars) {
        const result = [];
        bars.forEach((bar, index) => {
            const open = Number(bar.open);
            const high = Number(bar.high);
            const low = Number(bar.low);
            const close = Number(bar.close);
            const haClose = (open + high + low + close) / 4;
            const previous = result[index - 1];
            const haOpen = previous ? (previous.open + previous.close) / 2 : (open + close) / 2;
            result.push({
                ...bar,
                open: haOpen,
                high: Math.max(high, haOpen, haClose),
                low: Math.min(low, haOpen, haClose),
                close: haClose,
            });
        });
        return result;
    }

    function drawRenkoChart(ctx, layout) {
        const closes = layout.bars.map((bar) => Number(bar.close)).filter(Number.isFinite);
        if (closes.length < 2) return;
        const brickSize = Math.max(0.0000001, median(layout.bars.map((bar) => Math.abs(Number(bar.high) - Number(bar.low))).filter((v) => v > 0)) || Math.abs(closes[closes.length - 1] - closes[0]) / 20);
        let level = closes[0];
        const bricks = [];
        closes.forEach((close, index) => {
            while (Math.abs(close - level) >= brickSize && bricks.length < layout.bars.length) {
                const direction = close > level ? 1 : -1;
                const open = level;
                const brickClose = level + brickSize * direction;
                bricks.push({ index, open, close: brickClose, high: Math.max(open, brickClose), low: Math.min(open, brickClose) });
                level = brickClose;
            }
        });
        const visible = bricks.slice(-layout.bars.length);
        const w = clamp(layout.barStep * 0.75, 3, 16);
        visible.forEach((brick, i) => {
            const index = layout.barsFirstIndex + i;
            const xx = layout.x(index);
            const y1 = layout.y(brick.open);
            const y2 = layout.y(brick.close);
            ctx.fillStyle = brick.close >= brick.open ? '#089981' : '#f23645';
            ctx.fillRect(xx - w / 2, Math.min(y1, y2), w, Math.max(2, Math.abs(y2 - y1)));
        });
    }

    function drawMarketVolume(ctx, layout) {
        const { bars, barsFirstIndex, x, volumeArea, barStep } = layout;
        const maxVol = Math.max(1, ...bars.map((bar) => Number(bar.tick_volume || bar.real_volume || 0)));
        const volW = clamp(barStep * 0.55, 2, 12);
        const cutoff = replayCutoffIndex(layout.data);
        bars.forEach((bar, i) => {
            const index = barsFirstIndex + i;
            if (index > cutoff) return;
            const open = Number(bar.open);
            const close = Number(bar.close);
            const volume = Number(bar.tick_volume || bar.real_volume || 0);
            const h = (volume / maxVol) * volumeArea.height;
            const xx = x(index);
            ctx.fillStyle = close >= open ? 'rgba(8,153,129,0.35)' : 'rgba(242,54,69,0.35)';
            ctx.fillRect(xx - volW / 2, volumeArea.bottom - h, volW, h);
        });
    }

    function drawMarketIndicators(ctx, layout, analytics) {
        const indicators = state.chartView.indicators;
        const indicatorColors = state.chartView.indicatorColors || {};
        const data = layout.data;
        const legend = [];
        if (indicators.ema20) {
            const ema20Color = indicatorColors.ema20 || '#f59e0b';
            drawPriceSeries(ctx, layout, analytics.indicators.ema20, ema20Color, 1.7);
            legend.push(['EMA 20', ema20Color]);
        }
        if (indicators.ema50) {
            const ema50Color = indicatorColors.ema50 || '#2563eb';
            drawPriceSeries(ctx, layout, analytics.indicators.ema50, ema50Color, 1.7);
            legend.push(['EMA 50', ema50Color]);
        }
        if (indicators.ema200) {
            const ema200Color = bodyIsDark() ? '#f8fafc' : '#111827';
            drawPriceSeries(ctx, layout, analytics.indicators.ema200, ema200Color, 1.8);
            legend.push(['EMA 200', ema200Color]);
        }
        if (indicators.sma100) {
            drawPriceSeries(ctx, layout, analytics.indicators.sma100, '#7c3aed', 1.5);
            legend.push(['SMA 100', '#7c3aed']);
        }
        if (indicators.bollinger) {
            drawPriceSeries(ctx, layout, analytics.indicators.bbUpper, '#64748b', 1);
            drawPriceSeries(ctx, layout, analytics.indicators.bbMiddle, '#94a3b8', 1);
            drawPriceSeries(ctx, layout, analytics.indicators.bbLower, '#64748b', 1);
            legend.push(['Bollinger', '#64748b']);
        }
        if (indicators.vwap) {
            drawPriceSeries(ctx, layout, analytics.indicators.vwap, '#0f766e', 1.6);
            legend.push(['VWAP', '#0f766e']);
        }
        if (indicators.atrStop) {
            drawPriceSeries(ctx, layout, analytics.indicators.atrStop, '#be123c', 1.4, [5, 4]);
            legend.push(['Dừng ATR', '#be123c']);
        }
        if (layout.volumeArea) drawVolumeMa(ctx, layout, analytics);
        drawIndicatorLegend(ctx, layout, legend, data.symbol);
    }

    function drawCompareSeries(ctx, layout) {
        const symbols = state.chartView.compareSymbols || [];
        if (!symbols.length) return;
        const baseBars = layout.allBars || [];
        const baseIndex = clamp(layout.barsFirstIndex, 0, Math.max(0, baseBars.length - 1));
        const baseStart = Number(baseBars[baseIndex]?.close);
        if (!Number.isFinite(baseStart) || baseStart === 0) return;
        const colors = ['#7c3aed', '#0f766e', '#b36b00'];
        symbols.forEach((symbol, symbolIndex) => {
            const data = state.chartView.compareData[symbol];
            const bars = data?.bars || [];
            if (!bars.length) return;
            const compareStart = Number(bars[Math.min(baseIndex, bars.length - 1)]?.close);
            if (!Number.isFinite(compareStart) || compareStart === 0) return;
            ctx.save();
            ctx.strokeStyle = colors[symbolIndex % colors.length];
            ctx.lineWidth = 1.6;
            ctx.beginPath();
            let started = false;
            const sampleStep = chartSeriesSampleStep(layout);
            for (let index = layout.first; index < layout.lastExclusive; index += sampleStep) {
                if (index < 0 || index >= bars.length || index >= baseBars.length) {
                    started = false;
                    continue;
                }
                const bar = bars[Math.min(index, bars.length - 1)];
                const close = Number(bar?.close);
                if (!Number.isFinite(close)) continue;
                const relative = baseStart * (close / compareStart);
                const xx = layout.x(index);
                const yy = layout.y(relative);
                if (!started) {
                    ctx.moveTo(xx, yy);
                    started = true;
                } else {
                    ctx.lineTo(xx, yy);
                }
            }
            ctx.stroke();
            ctx.fillStyle = colors[symbolIndex % colors.length];
            ctx.font = '700 11px Segoe UI, Arial';
            ctx.fillText(symbol, layout.priceArea.left + 8, layout.priceArea.top + 34 + symbolIndex * 15);
            ctx.restore();
        });
    }

    function drawDataWarnings(ctx, layout, analytics) {
        if (state.activeTab === 'pa-training') return;
        if (!state.chartView.overlays.warnings) return;
        const cutoff = replayCutoffIndex(layout.data);
        const warningMap = {
            gap: '#b36b00',
            zero_volume: '#7c3aed',
            abnormal_range: '#bc3d3d',
            high_spread: '#0f766e',
        };
        analytics.warnings.forEach((warning) => {
            const index = Number(warning.index);
            if (!Number.isFinite(index) || index < layout.first || index >= layout.lastExclusive || index > cutoff) return;
            const xx = layout.x(index);
            const color = warningMap[warning.type] || '#b36b00';
            ctx.save();
            ctx.strokeStyle = color;
            ctx.fillStyle = color;
            ctx.globalAlpha = warning.type === 'gap' ? 0.16 : 0.85;
            if (warning.type === 'gap') {
                ctx.fillRect(Math.max(layout.priceArea.left, xx - layout.barStep / 2), layout.priceArea.top, Math.max(2, layout.barStep), layout.panelBottom - layout.priceArea.top);
            } else {
                ctx.beginPath();
                ctx.moveTo(xx, layout.priceArea.top + 6);
                ctx.lineTo(xx - 5, layout.priceArea.top + 18);
                ctx.lineTo(xx + 5, layout.priceArea.top + 18);
                ctx.closePath();
                ctx.fill();
            }
            ctx.restore();
        });
    }

    function drawStrategyDebugOverlay(ctx, layout, analytics) {
        if (state.activeTab === 'pa-training') return;
        const run = activeBacktestRun(analytics);
        const debug = run?.debug || activeDebugPayloadForChart(layout.data);
        if (!debug) return;
        if (state.chartView.overlays.zones) {
            drawH4Zones(ctx, layout, debug.h4Zones || []);
        }
        if (state.chartView.overlays.swings) {
            drawM15Swings(ctx, layout, debug.m15Structure?.swings || []);
        }
    }

    function activeDebugPayloadForChart(data) {
        const latest = state.lastServerBacktest;
        const symbol = String(data?.symbol || '').toUpperCase();
        if (!latest?.debug || String(latest.symbol || latest.summary?.symbol || '').toUpperCase() !== symbol) {
            return null;
        }
        return latest.debug;
    }

    function drawH4Zones(ctx, layout, zones) {
        const visibleZones = zones.filter((zone) => Number.isFinite(Number(zone.high)) && Number.isFinite(Number(zone.low)));
        if (!visibleZones.length) return;
        ctx.save();
        visibleZones.forEach((zone) => {
            const start = chartIndexForTime(layout, zone.originCandleTime || zone.createdAt);
            const x1 = Number.isFinite(start) ? Math.max(layout.priceArea.left, layout.x(start)) : layout.priceArea.left;
            const x2 = layout.priceArea.right;
            if (x2 < layout.priceArea.left || x1 > layout.priceArea.right) return;
            const top = layout.y(Number(zone.high));
            const bottom = layout.y(Number(zone.low));
            const color = zone.type === 'demand' ? '#0f766e' : '#b42318';
            const highlighted = (zone.id || zone.zoneId || '') === state.chartView.highlightedZoneId;
            ctx.globalAlpha = zone.broken ? 0.10 : 0.18;
            ctx.fillStyle = color;
            ctx.fillRect(x1, Math.min(top, bottom), Math.max(2, x2 - x1), Math.max(2, Math.abs(bottom - top)));
            ctx.globalAlpha = highlighted ? 1 : (zone.broken ? 0.35 : 0.85);
            ctx.strokeStyle = color;
            ctx.lineWidth = highlighted ? 3 : 1;
            ctx.strokeRect(x1, Math.min(top, bottom), Math.max(2, x2 - x1), Math.max(2, Math.abs(bottom - top)));
            ctx.fillStyle = color;
            ctx.font = '700 10px Segoe UI, Arial';
            const label = `${displayZone(zone.type || '')} ${formatFixed(zone.strengthScore || 0, 0)}`;
            ctx.fillText(label, Math.max(layout.priceArea.left + 4, x1 + 4), Math.min(top, bottom) - 4);
        });
        ctx.restore();
    }

    function drawM15Swings(ctx, layout, swings) {
        const cutoff = replayCutoffIndex(layout.data);
        ctx.save();
        swings.forEach((swing) => {
            const index = Number(swing.sourceIndex ?? swing.index);
            const price = Number(swing.price);
            if (!Number.isFinite(index) || !Number.isFinite(price)) return;
            if (index < layout.first || index >= layout.lastExclusive || index > cutoff) return;
            const xx = layout.x(index);
            const yy = layout.y(price);
            const isHigh = swing.type === 'swingHigh';
            ctx.fillStyle = isHigh ? '#b42318' : '#0f766e';
            ctx.beginPath();
            ctx.arc(xx, yy, 3.2, 0, Math.PI * 2);
            ctx.fill();
            ctx.font = '700 9px Segoe UI, Arial';
            ctx.fillText(isHigh ? 'SH' : 'SL', xx + 4, yy + (isHigh ? -5 : 12));
        });
        ctx.restore();
    }

    function chartIndexForTime(layout, value) {
        const ts = timeToSeconds(value);
        if (!Number.isFinite(ts)) return NaN;
        const bars = layout.allBars || [];
        let best = NaN;
        let bestDistance = Infinity;
        bars.forEach((bar, index) => {
            const diff = Math.abs(barTimeValue(bar) - ts);
            if (diff < bestDistance) {
                bestDistance = diff;
                best = index;
            }
        });
        return best;
    }

    function timeToSeconds(value) {
        if (Number.isFinite(Number(value))) return Number(value);
        const date = new Date(value || '');
        return Number.isNaN(date.getTime()) ? NaN : Math.floor(date.getTime() / 1000);
    }

    function drawBacktestTrades(ctx, layout, analytics) {
        if (state.activeTab === 'pa-training') return;
        const run = activeBacktestRun(analytics);
        if (!run) return;
        const trades = filteredTrades(run.trades);
        const cutoff = replayCutoffIndex(layout.data);
        trades.forEach((trade) => drawBacktestTrade(ctx, layout, trade, cutoff));
    }

    function drawBacktestTrade(ctx, layout, trade, cutoff) {
        if (trade.entryIndex > cutoff) return;
        const endIndex = Math.min(trade.exitIndex, cutoff);
        if (endIndex < layout.first || trade.entryIndex >= layout.lastExclusive) return;

        const selected = trade.id === state.chartView.selectedTradeId;
        const isLong = trade.direction === 'long';
        const win = trade.netR > 0;
        const color = isLong ? '#089981' : '#f23645';
        const resultColor = win ? '#089981' : trade.netR < 0 ? '#f23645' : '#64748b';
        const x1 = clamp(layout.x(trade.entryIndex), layout.priceArea.left, layout.priceArea.right);
        const x2 = clamp(layout.x(endIndex), layout.priceArea.left, layout.priceArea.right);
        const entryY = layout.y(trade.entryPrice);
        const exitY = layout.y(trade.exitPrice);
        const targetY = layout.y(trade.targetPrice);
        const stopY = layout.y(trade.initialStop);

        ctx.save();
        if (state.chartView.overlays.positionBands) {
            const top = Math.min(entryY, exitY, targetY, stopY);
            const bottom = Math.max(entryY, exitY, targetY, stopY);
            ctx.fillStyle = isLong ? 'rgba(8, 153, 129, 0.08)' : 'rgba(242, 54, 69, 0.08)';
            ctx.fillRect(x1, top, Math.max(2, x2 - x1), bottom - top);
        }

        if (state.chartView.overlays.slTp) {
            ctx.lineWidth = selected ? 1.8 : 1.2;
            ctx.setLineDash([6, 4]);
            drawTradeLevel(ctx, layout, trade.targetPrice, x1, x2, '#089981', 'TP');
            drawTradeLevel(ctx, layout, trade.initialStop, x1, x2, '#f23645', 'SL');
            if (trade.beIndex && trade.beIndex <= endIndex) {
                drawTradeLevel(ctx, layout, trade.entryPrice, layout.x(trade.beIndex), x2, '#64748b', 'BE');
            }
            ctx.setLineDash([]);
        }

        ctx.strokeStyle = selected ? '#111827' : resultColor;
        ctx.lineWidth = selected ? 2.8 : 1.8;
        ctx.beginPath();
        ctx.moveTo(x1, entryY);
        ctx.lineTo(x2, exitY);
        ctx.stroke();

        if (state.chartView.overlays.markers) {
            drawTradeMarker(ctx, x1, entryY, isLong ? 'up' : 'down', color, selected, 'Vào');
            if (trade.exitIndex <= cutoff) {
                drawTradeMarker(ctx, x2, exitY, isLong ? 'down' : 'up', resultColor, selected, trade.exitType);
            }
        }
        ctx.restore();
    }

    function paNearbyZones(data = state.activeMarketData) {
        const bars = data?.bars || [];
        const cutoff = state.chartView.replay.enabled ? replayCutoffIndex(data) : bars.length - 1;
        const trade = paTrainingSelectedTeachingCase();
        const direction = trade && sameSymbol(trade.symbol, data?.symbol) ? trade.direction : 'long';
        if (paNearbyZoneCache?.bars === bars && paNearbyZoneCache.cutoff === cutoff && paNearbyZoneCache.direction === direction) return paNearbyZoneCache.result;
        const result = window.PaZones?.nearest(bars, cutoff, direction) || {entries: [], targets: []};
        paNearbyZoneCache = {bars, cutoff, direction, result};
        return result;
    }

    function drawPaNearbyZones(ctx, layout) {
        if (state.activeTab !== 'pa-training' || !paNearbyZonesVisible) return;
        const nearby = paNearbyZones(layout.data);
        const zones = nearby.targets.map(zone => ({...zone, color: '#059669'}));
        ctx.save();
        ctx.beginPath(); ctx.rect(layout.priceArea.left, layout.priceArea.top, layout.priceArea.width, layout.priceArea.height); ctx.clip();
        zones.forEach((zone, index) => {
            const left = Math.max(layout.priceArea.left, Math.min(layout.x(zone.originIndex), layout.priceArea.right - 130));
            const top = layout.y(zone.high), bottom = layout.y(zone.low);
            ctx.globalAlpha = .09; ctx.fillStyle = zone.color;
            ctx.fillRect(left, top, layout.priceArea.right - left, Math.max(3, bottom - top));
            ctx.globalAlpha = .8; ctx.strokeStyle = zone.color; ctx.setLineDash([6, 4]);
            ctx.strokeRect(left, top, layout.priceArea.right - left, Math.max(3, bottom - top));
        });
        ctx.restore();
        const note = $('#paNearbyZonesNote');
        if (note) note.textContent = `${layout.data.timeframe} · ${nearby.targets.length}/2 vùng TP (${nearby.direction === 'short' ? 'bán' : 'mua'})`;
    }

    function drawPaTrainingTeachingCaseOverlay(ctx, layout) {
        if (state.activeTab !== 'pa-training') return;
        const trade = paTrainingSelectedTeachingCase();
        if (!trade || !layout?.data || !paTrainingCaseFitsChartData(trade, layout.data)) return;
        const entry = Number(trade.entryPrice);
        const sl = Number(trade.initialStop);
        const tp = Number(trade.targetPrice);
        const risk = Math.abs(entry - sl);
        if (![entry, sl, tp].every(Number.isFinite) || risk <= 0) return;
        const indexFor = (time, fallback) => {
            const byTime = chartIndexForTime(layout, time);
            if (Number.isFinite(byTime)) return byTime;
            if (String(layout.data.timeframe || '').toUpperCase() === String(trade.timeframe || '').toUpperCase() && Number.isFinite(Number(fallback))) return Number(fallback);
            return NaN;
        };
        const entryIndex = indexFor(trade.entryTime || trade.anchorTime, trade.entryIndex ?? trade.anchorIndex);
        if (!Number.isFinite(entryIndex)) return;
        const xEntry = clamp(layout.x(entryIndex), layout.priceArea.left, layout.priceArea.right);
        const tpColor = '#059669';
        const slColor = '#f23645';
        const bandRight = layout.priceArea.right;
        const boxRight = Math.min(bandRight, xEntry + clamp(layout.priceArea.width * 0.18, 150, 250));
        ctx.save();
        const yEntry = layout.y(entry), ySl = layout.y(sl), yTp = layout.y(tp);
        const rewardTop = Math.min(yEntry, yTp), rewardBottom = Math.max(yEntry, yTp);
        const riskTop = Math.min(yEntry, ySl), riskBottom = Math.max(yEntry, ySl);
        ctx.globalAlpha = 0.17;
        ctx.fillStyle = tpColor;
        ctx.fillRect(xEntry, rewardTop, Math.max(2, boxRight - xEntry), Math.max(2, rewardBottom - rewardTop));
        ctx.globalAlpha = 0.85;
        ctx.strokeStyle = tpColor;
        ctx.lineWidth = 1.5;
        ctx.strokeRect(xEntry, rewardTop, Math.max(2, boxRight - xEntry), Math.max(2, rewardBottom - rewardTop));
        ctx.globalAlpha = 0.17;
        ctx.fillStyle = slColor;
        ctx.fillRect(xEntry, riskTop, Math.max(2, boxRight - xEntry), Math.max(2, riskBottom - riskTop));
        ctx.globalAlpha = 0.85;
        ctx.strokeStyle = slColor;
        ctx.strokeRect(xEntry, riskTop, Math.max(2, boxRight - xEntry), Math.max(2, riskBottom - riskTop));
        ctx.globalAlpha = 1;
        ctx.setLineDash([6, 4]);
        drawTradeLevel(ctx, layout, entry, xEntry, bandRight, '#2563eb');
        drawTradeLevel(ctx, layout, sl, xEntry, bandRight, slColor);
        drawTradeLevel(ctx, layout, tp, xEntry, bandRight, tpColor);
        const tags = [
            { label: `TP · RR ${formatFixed(Math.abs(tp - entry) / risk, 2)}R`, price: tp, color: tpColor },
            { label: 'Entry', price: entry, color: '#2563eb' },
            { label: 'SL', price: sl, color: slColor },
        ];
        ctx.setLineDash([]);
        ctx.font = '700 11px Segoe UI, Arial';
        tags.forEach(({label, price, color}) => {
            const text = `${label} ${formatPrice(price, trade.symbol)}`;
            const width = ctx.measureText(text).width + 12;
            const y = clamp(layout.y(price) - 9, layout.priceArea.top + 2, layout.priceArea.bottom - 21);
            ctx.fillStyle = color;
            const labelX = Math.max(xEntry + 4, bandRight - width - 4);
            ctx.fillRect(labelX, y, width, 19);
            ctx.fillStyle = '#fff';
            ctx.fillText(text, labelX + 6, y + 13);
        });
        if (state.chartView.teachingLevelMarkersVisible !== false) {
            drawTeachingCasePointMarker(ctx, xEntry, yEntry, 'ENTRY', '#2563eb', layout);
            drawTeachingCasePointMarker(ctx, xEntry, ySl, 'SL', slColor, layout);
            drawTeachingCasePointMarker(ctx, xEntry, yTp, 'TP', tpColor, layout);
        }
        ctx.setLineDash([]);
        ctx.restore();
    }

    function drawTeachingCasePointMarker(ctx, x, y, label, color, layout) {
        const badgeWidth = label === 'ENTRY' ? 46 : 25;
        const badgeHeight = 17;
        const badgeX = x + badgeWidth + 16 <= layout.priceArea.right ? x + 10 : x - badgeWidth - 10;
        const badgeY = clamp(y - badgeHeight / 2, layout.priceArea.top + 2, layout.priceArea.bottom - badgeHeight - 2);
        ctx.save();
        ctx.globalAlpha = 0.96;
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(x, y, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.fillStyle = color;
        ctx.fillRect(badgeX, badgeY, badgeWidth, badgeHeight);
        ctx.font = '700 9px Segoe UI, Arial';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#fff';
        ctx.fillText(label, badgeX + 4, badgeY + badgeHeight / 2);
        ctx.restore();
    }

    function drawPaTrainingLevelOverlay(layout = state.chartView.lastLayout) {
        const chart = $('#marketChart');
        if (!chart?.parentElement) return;
        let overlay = $('#paTradeLevelOverlay');
        if (!overlay) {
            overlay = document.createElement('canvas');
            overlay.id = 'paTradeLevelOverlay';
            overlay.setAttribute('aria-hidden', 'true');
            chart.parentElement.appendChild(overlay);
        }
        const rect = chart.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        overlay.style.left = `${chart.offsetLeft}px`;
        overlay.style.top = `${chart.offsetTop}px`;
        overlay.style.width = `${rect.width}px`;
        overlay.style.height = `${rect.height}px`;
        const width = Math.max(1, Math.floor(rect.width * dpr));
        const height = Math.max(1, Math.floor(rect.height * dpr));
        if (overlay.width !== width || overlay.height !== height) {
            overlay.width = width;
            overlay.height = height;
        }
        const ctx = overlay.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, rect.width, rect.height);
        if (layout && state.activeTab === 'pa-training') drawPaTrainingTeachingCaseOverlay(ctx, layout);
    }
    function drawIndicatorPanels(ctx, layout, analytics) {
        if (layout.rsiArea) {
            drawPanelFrame(ctx, layout.rsiArea, 'RSI 14');
            const area = layout.rsiArea;
            const y = (value) => area.bottom - (clamp(value, 0, 100) / 100) * area.height;
            ctx.save();
            ctx.strokeStyle = '#d8dde3';
            [30, 50, 70].forEach((level) => {
                const yy = y(level);
                ctx.beginPath();
                ctx.moveTo(area.left, yy);
                ctx.lineTo(area.right, yy);
                ctx.stroke();
                ctx.fillStyle = '#65717f';
                ctx.font = '11px Segoe UI, Arial';
                ctx.fillText(String(level), area.right - 24, yy - 3);
            });
            drawPanelSeries(ctx, layout, analytics.indicators.rsi, area, 0, 100, '#7c3aed', 1.5);
            ctx.restore();
        }

        if (layout.macdArea) {
            drawPanelFrame(ctx, layout.macdArea, 'MACD 12/26/9');
            const area = layout.macdArea;
            const values = [
                ...analytics.indicators.macdLine,
                ...analytics.indicators.macdSignal,
                ...analytics.indicators.macdHist,
            ].filter(Number.isFinite);
            const maxAbs = Math.max(0.0000001, ...values.map((value) => Math.abs(value)));
            const y = (value) => area.top + ((maxAbs - value) / (maxAbs * 2)) * area.height;
            const zeroY = y(0);
            ctx.save();
            ctx.strokeStyle = '#d8dde3';
            ctx.beginPath();
            ctx.moveTo(area.left, zeroY);
            ctx.lineTo(area.right, zeroY);
            ctx.stroke();
            const histW = clamp(layout.barStep * 0.52, 1, 8);
            const sampleStep = chartSeriesSampleStep(layout);
            for (let index = layout.first; index < layout.lastExclusive; index += sampleStep) {
                if (index > replayCutoffIndex(layout.data)) break;
                const value = analytics.indicators.macdHist[index];
                if (!Number.isFinite(value)) continue;
                const xx = layout.x(index);
                ctx.fillStyle = value >= 0 ? 'rgba(8,153,129,0.5)' : 'rgba(242,54,69,0.5)';
                ctx.fillRect(xx - histW / 2, Math.min(zeroY, y(value)), histW, Math.max(1, Math.abs(y(value) - zeroY)));
            }
            drawPanelSeries(ctx, layout, analytics.indicators.macdLine, area, -maxAbs, maxAbs, '#2563eb', 1.4);
            drawPanelSeries(ctx, layout, analytics.indicators.macdSignal, area, -maxAbs, maxAbs, '#f59e0b', 1.2);
            ctx.restore();
        }
    }

    function drawBacktestResultPanel(ctx, layout, analytics) {
        const area = layout.equityArea;
            drawPanelFrame(ctx, area, 'Tài sản / sụt giảm / lời lỗ');
        const equityArea = {
            left: area.left,
            right: area.right,
            top: area.top + 18,
            bottom: area.top + Math.max(54, area.height * 0.6),
            width: area.width,
            height: Math.max(34, area.height * 0.6 - 18),
        };
        const ddArea = {
            left: area.left,
            right: area.right,
            top: equityArea.bottom + 8,
            bottom: area.bottom - 4,
            width: area.width,
            height: Math.max(24, area.bottom - equityArea.bottom - 12),
        };
        const allEquity = analytics.runs.flatMap((run) => run.equityCurve.map((point) => point.equity)).filter(Number.isFinite);
        const minEq = Math.min(...allEquity, 10000);
        const maxEq = Math.max(...allEquity, 10000);
        const eqPad = Math.max(20, (maxEq - minEq) * 0.12);
        analytics.runs.forEach((run) => {
            const active = run.id === state.chartView.activeRunId;
            drawEquityCurve(ctx, layout, run, equityArea, minEq - eqPad, maxEq + eqPad, active ? run.color : '#94a3b8', active ? 2.2 : 1.2);
        });
        const activeRun = activeBacktestRun(analytics);
        if (!activeRun) return;
        drawDrawdownAndPnl(ctx, layout, activeRun, ddArea);

        ctx.save();
        ctx.font = '11px Segoe UI, Arial';
        let x = area.left + 8;
        analytics.runs.forEach((run) => {
            ctx.fillStyle = run.id === state.chartView.activeRunId ? run.color : '#94a3b8';
            ctx.fillRect(x, area.top + 6, 9, 9);
            ctx.fillStyle = '#65717f';
            ctx.fillText(run.name, x + 13, area.top + 15);
            x += Math.max(72, ctx.measureText(run.name).width + 28);
        });
        ctx.restore();
    }

    function drawPriceSeries(ctx, layout, series, color, lineWidth = 1.5, dash = null) {
        drawSeriesByIndex(ctx, layout, series, layout.priceArea, layout.lo, layout.hi, color, lineWidth, dash);
    }

    function drawPanelSeries(ctx, layout, series, area, min, max, color, lineWidth = 1.4) {
        drawSeriesByIndex(ctx, layout, series, area, min, max, color, lineWidth);
    }

    function chartSeriesSampleStep(layout) {
        if (!layout || layout.barStep >= 0.85) return 1;
        return clamp(Math.floor(0.85 / Math.max(0.0001, layout.barStep)), 1, 60);
    }

    function drawSeriesByIndex(ctx, layout, series, area, min, max, color, lineWidth = 1.5, dash = null) {
        const cutoff = replayCutoffIndex(layout.data);
        const y = (value) => area.top + ((max - value) / Math.max(0.0000001, max - min)) * area.height;
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = lineWidth;
        if (dash) ctx.setLineDash(dash);
        ctx.beginPath();
        let started = false;
        const sampleStep = chartSeriesSampleStep(layout);
        for (let index = layout.first; index < layout.lastExclusive; index += sampleStep) {
            if (index > cutoff) break;
            const value = series[index];
            if (!Number.isFinite(value)) {
                started = false;
                continue;
            }
            const xx = layout.x(index);
            const yy = y(value);
            if (!started) {
                ctx.moveTo(xx, yy);
                started = true;
            } else {
                ctx.lineTo(xx, yy);
            }
        }
        ctx.stroke();
        ctx.restore();
    }

    function drawVolumeMa(ctx, layout, analytics) {
        const area = layout.volumeArea;
        const volumes = layout.bars.map((bar) => Number(bar.tick_volume || bar.real_volume || 0));
        const maxVol = Math.max(1, ...volumes, ...analytics.indicators.volumeMa.slice(layout.barsFirstIndex, layout.barsLastExclusive).filter(Number.isFinite));
        drawSeriesByIndex(ctx, layout, analytics.indicators.volumeMa, area, 0, maxVol, '#64748b', 1.2, [4, 4]);
        ctx.save();
        ctx.fillStyle = '#65717f';
        ctx.font = '11px Segoe UI, Arial';
        ctx.fillText('MA khối lượng 20', area.left + 8, area.top + 14);
        ctx.restore();
    }

    function drawIndicatorLegend(ctx, layout, legend) {
        if (!legend.length) return;
        ctx.save();
        ctx.font = '12px Segoe UI, Arial';
        let x = layout.priceArea.left + 8;
        const y = layout.priceArea.top + 18;
        legend.forEach(([label, color]) => {
            ctx.fillStyle = color;
            ctx.fillRect(x, y - 8, 9, 9);
            ctx.fillStyle = '#65717f';
            ctx.fillText(label, x + 13, y);
            x += Math.max(70, ctx.measureText(label).width + 28);
        });
        ctx.restore();
    }

    function drawPanelFrame(ctx, area, title) {
        ctx.save();
        ctx.fillStyle = bodyIsDark() ? '#0f1720' : '#f8fafc';
        ctx.fillRect(area.left, area.top, area.width, area.height);
        ctx.strokeStyle = '#d8dde3';
        ctx.beginPath();
        ctx.moveTo(area.left, area.top);
        ctx.lineTo(area.right, area.top);
        ctx.stroke();
        ctx.fillStyle = '#65717f';
        ctx.font = '11px Segoe UI, Arial';
        ctx.fillText(title, area.left + 8, area.top + 14);
        ctx.restore();
    }

    function drawTradeLevel(ctx, layout, price, x1, x2, color, label) {
        const yy = layout.y(price);
        ctx.strokeStyle = color;
        ctx.beginPath();
        ctx.moveTo(x1, yy);
        ctx.lineTo(x2, yy);
        ctx.stroke();
        if (label && x2 - x1 > 60) {
            ctx.fillStyle = color;
            ctx.font = '700 10px Segoe UI, Arial';
            ctx.fillText(label, x1 + 4, yy - 4);
        }
    }

    function drawTradeMarker(ctx, x, y, direction, color, selected, label) {
        const size = selected ? 9 : 7;
        ctx.save();
        ctx.fillStyle = color;
        ctx.strokeStyle = selected ? '#111827' : '#ffffff';
        ctx.lineWidth = selected ? 2 : 1.3;
        ctx.beginPath();
        if (direction === 'up') {
            ctx.moveTo(x, y - size);
            ctx.lineTo(x - size, y + size);
            ctx.lineTo(x + size, y + size);
        } else {
            ctx.moveTo(x, y + size);
            ctx.lineTo(x - size, y - size);
            ctx.lineTo(x + size, y - size);
        }
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        if (selected && label) {
            ctx.fillStyle = '#111827';
            ctx.font = '700 10px Segoe UI, Arial';
            ctx.fillText(String(label).slice(0, 3), x + 10, y + 3);
        }
        ctx.restore();
    }

    function drawEquityCurve(ctx, layout, run, area, min, max, color, lineWidth) {
        const cutoff = replayCutoffIndex(layout.data);
        const points = [{ index: 0, equity: 10000 }, ...run.equityCurve].filter((point) => point.index <= cutoff);
        const y = (value) => area.top + ((max - value) / Math.max(0.0000001, max - min)) * area.height;
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = lineWidth;
        ctx.beginPath();
        let started = false;
        points.forEach((point) => {
            if (point.index < layout.first || point.index >= layout.lastExclusive) return;
            const xx = layout.x(point.index);
            const yy = y(point.equity);
            if (!started) {
                ctx.moveTo(xx, yy);
                started = true;
            } else {
                ctx.lineTo(xx, yy);
            }
        });
        ctx.stroke();
        ctx.restore();
    }

    function drawDrawdownAndPnl(ctx, layout, run, area) {
        const cutoff = replayCutoffIndex(layout.data);
        const minDd = Math.min(-0.01, ...run.drawdownCurve.map((point) => point.drawdownPct));
        const y = (value) => area.top + ((0 - value) / Math.max(0.01, 0 - minDd)) * area.height;
        ctx.save();
        ctx.strokeStyle = '#d8dde3';
        ctx.beginPath();
        ctx.moveTo(area.left, y(0));
        ctx.lineTo(area.right, y(0));
        ctx.stroke();
        ctx.strokeStyle = '#bc3d3d';
        ctx.lineWidth = 1.4;
        ctx.beginPath();
        let started = false;
        run.drawdownCurve.forEach((point) => {
            if (point.index > cutoff || point.index < layout.first || point.index >= layout.lastExclusive) return;
            const xx = layout.x(point.index);
            const yy = y(point.drawdownPct);
            if (!started) {
                ctx.moveTo(xx, yy);
                started = true;
            } else {
                ctx.lineTo(xx, yy);
            }
        });
        ctx.stroke();
        const barW = clamp(layout.barStep * 0.65, 1, 8);
        run.trades.forEach((trade) => {
            if (trade.exitIndex > cutoff || trade.exitIndex < layout.first || trade.exitIndex >= layout.lastExclusive) return;
            const xx = layout.x(trade.exitIndex);
            const h = clamp(Math.abs(trade.netR) / 4, 0.04, 1) * area.height * 0.42;
            ctx.fillStyle = trade.netR >= 0 ? 'rgba(8,153,129,0.55)' : 'rgba(242,54,69,0.55)';
            if (trade.netR >= 0) {
                ctx.fillRect(xx - barW / 2, y(0) - h, barW, h);
            } else {
                ctx.fillRect(xx - barW / 2, y(0), barW, h);
            }
        });
        ctx.fillStyle = '#65717f';
        ctx.font = '11px Segoe UI, Arial';
        ctx.fillText('Sụt giảm / lời lỗ từng lệnh', area.left + 8, area.top + 12);
        ctx.restore();
    }

    function drawMarketAxis(ctx, layout, symbol) {
        const { priceArea, axisArea, hi, lo } = layout;
        ctx.fillStyle = bodyIsDark() ? '#111923' : '#ffffff';
        ctx.fillRect(axisArea.left, axisArea.top, axisArea.width, axisArea.bottom - axisArea.top);
        ctx.strokeStyle = bodyIsDark() ? '#2e3b49' : '#d8dde3';
        ctx.beginPath();
        ctx.moveTo(axisArea.left, axisArea.top);
        ctx.lineTo(axisArea.left, axisArea.bottom);
        ctx.stroke();
        ctx.font = '12px Segoe UI, Arial';
        ctx.fillStyle = bodyIsDark() ? '#a8b3c1' : '#17202a';
        for (let i = 0; i <= 8; i++) {
            const scaled = Number.isFinite(layout.scaleHi) && Number.isFinite(layout.scaleLo)
                ? layout.scaleHi - (i / 8) * (layout.scaleHi - layout.scaleLo)
                : hi - (i / 8) * (hi - lo);
            const price = layout.fromScaleValue ? layout.fromScaleValue(scaled) : scaled;
            const yy = priceArea.top + (i / 8) * priceArea.height;
            const label = layout.scaleMode === 'percent' ? `${formatSigned(scaled, 2)}%` : formatPrice(price, symbol);
            ctx.fillText(label, axisArea.left + 8, yy + 4);
        }
        const last = layout.allBars[Math.min(layout.allBars.length - 1, layout.barsLastExclusive - 1)] || layout.allBars[layout.allBars.length - 1];
        if (last) {
            const close = Number(last.close);
            const yy = layout.y(close);
            ctx.fillStyle = '#089981';
            ctx.fillRect(axisArea.left + 3, yy - 14, axisArea.width - 8, 28);
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 12px Segoe UI, Arial';
            ctx.fillText(formatPrice(close, symbol), axisArea.left + 8, yy + 4);
        }
    }

    function drawMarketFooter(ctx, layout, data) {
        const { priceArea, bars, barsFirstIndex } = layout;
        ctx.fillStyle = bodyIsDark() ? '#edf2f7' : '#17202a';
        ctx.font = '600 13px Segoe UI, Arial';
        ctx.fillText(`${data.symbol} · ${data.timeframe}`, priceArea.left + 8, 18);
        ctx.fillStyle = bodyIsDark() ? '#a8b3c1' : '#65717f';
        ctx.font = '12px Segoe UI, Arial';
        const markerCount = Math.max(1, Math.min(6, Math.floor(priceArea.width / 110)));
        const markerEvery = Math.max(1, Math.floor(bars.length / markerCount));
        let previousLabelRight = -Infinity;
        bars.forEach((bar, i) => {
            if (i % markerEvery !== 0 && i !== bars.length - 1) return;
            const xx = layout.x(barsFirstIndex + i);
            const label = formatChartTime(bar.time_iso || bar.time);
            const labelWidth = ctx.measureText(label).width;
            const labelLeft = Math.max(priceArea.left, Math.min(xx - labelWidth / 2, priceArea.right - labelWidth));
            if (labelLeft < previousLabelRight + 14) return;
            ctx.fillText(label, labelLeft, layout.height - 16);
            previousLabelRight = labelLeft + labelWidth;
        });
    }

    function drawMarketChartCrosshairOverlay(layout = state.chartView.lastLayout, data = state.activeMarketData) {
        const chart = $('#marketChart');
        if (!chart?.parentElement) return;
        let overlay = $('#marketChartCrosshair');
        if (!overlay) {
            overlay = document.createElement('canvas');
            overlay.id = 'marketChartCrosshair';
            overlay.setAttribute('aria-hidden', 'true');
            chart.parentElement.appendChild(overlay);
        }
        const dpr = window.devicePixelRatio || 1;
        if (overlay.width !== chart.width) overlay.width = chart.width;
        if (overlay.height !== chart.height) overlay.height = chart.height;
        const ctx = overlay.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, chart.width / dpr, chart.height / dpr);
        if (layout && data) drawChartCrosshair(ctx, layout, data);
    }

    function drawChartCrosshair(ctx, layout, data) {
        const point = state.chartView.crosshairPoint;
        if (!point) return;
        const { priceArea, axisArea, panelBottom } = layout;
        if (point.x < priceArea.left || point.x > priceArea.right || point.y < priceArea.top || point.y > panelBottom) {
            return;
        }

        const xx = clamp(point.x, priceArea.left, priceArea.right);
        const yy = clamp(point.y, priceArea.top, panelBottom);
        ctx.save();
        ctx.strokeStyle = 'rgba(75, 85, 99, 0.72)';
        ctx.lineWidth = 1;
        ctx.setLineDash([5, 5]);
        ctx.beginPath();
        ctx.moveTo(xx, priceArea.top);
        ctx.lineTo(xx, panelBottom);
        ctx.stroke();

        if (yy >= priceArea.top && yy <= priceArea.bottom) {
            ctx.beginPath();
            ctx.moveTo(priceArea.left, yy);
            ctx.lineTo(priceArea.right, yy);
            ctx.stroke();

            const priceLabel = formatPrice(layout.priceAtY(yy), data.symbol);
            ctx.setLineDash([]);
            ctx.font = '700 12px Segoe UI, Arial';
            const labelW = Math.min(axisArea.width - 8, Math.max(52, ctx.measureText(priceLabel).width + 12));
            ctx.fillStyle = '#1f2937';
            ctx.fillRect(axisArea.left + 3, yy - 12, labelW, 24);
            ctx.fillStyle = '#ffffff';
            ctx.fillText(priceLabel, axisArea.left + 9, yy + 4);
            ctx.setLineDash([5, 5]);
        }

        const barIndex = Math.round(layout.barIndexAtX(xx));
        const bar = barIndex >= 0 && barIndex < layout.allBars.length ? layout.allBars[barIndex] : null;
        const timeLabel = formatChartTime(bar?.time_iso || bar?.time);
        if (timeLabel) {
            ctx.setLineDash([]);
            ctx.font = '700 12px Segoe UI, Arial';
            const textW = Math.max(74, ctx.measureText(timeLabel).width + 16);
            const labelX = clamp(xx - textW / 2, priceArea.left, priceArea.right - textW);
            const labelY = layout.height - 35;
            ctx.fillStyle = '#1f2937';
            ctx.fillRect(labelX, labelY - 12, textW, 24);
            ctx.fillStyle = '#ffffff';
            ctx.fillText(timeLabel, labelX + 8, labelY + 4);
        }
        ctx.restore();
    }

    function drawMarketDrawings(ctx, layout, data) {
        if (!state.chartView.drawingsVisible) return;
        const drawings = getChartDrawings(data);
        const draft = state.chartView.draftDrawing;
        const selectedIds = new Set(state.chartView.selectedDrawingIds || []);
        drawings.forEach((drawing) => {
            if (!drawing.hidden) drawSingleDrawing(ctx, layout, drawing, selectedIds.has(getDrawingId(drawing)));
        });
        if (draft) drawSingleDrawing(ctx, layout, draft, false);
        drawSelectionBox(ctx, layout);
    }

    function drawSingleDrawing(ctx, layout, drawing, selected = false) {
        if (!drawing || drawing.hidden) return;
        const color = drawing.color || '#1f6feb';
        ctx.save();
        ctx.strokeStyle = selected ? '#0f766e' : color;
        ctx.fillStyle = color;
        ctx.lineWidth = selected ? Number(drawing.width || 2) + 1 : Number(drawing.width || 2);
        applyDrawingLineStyle(ctx, drawing);
        if (drawing.type === 'hline' || drawing.type === 'hray') {
            const yy = layout.y(drawing.price);
            ctx.setLineDash([8, 5]);
            ctx.beginPath();
            ctx.moveTo(drawing.type === 'hray' ? layout.x(drawing.startBar || layout.first) : layout.priceArea.left, yy);
            ctx.lineTo(layout.priceArea.right, yy);
            ctx.stroke();
            drawDrawingTag(ctx, layout.priceArea.right - 6, yy, drawing.label || formatPrice(drawing.price, layout.data.symbol), color, 'right');
        } else if (drawing.type === 'alert') {
            const yy = layout.y(drawing.price);
            ctx.setLineDash([3, 4]);
            ctx.beginPath();
            ctx.moveTo(layout.priceArea.left, yy);
            ctx.lineTo(layout.priceArea.right, yy);
            ctx.stroke();
            drawDrawingTag(ctx, layout.priceArea.right - 6, yy, drawing.label || `Cảnh báo ${formatPrice(drawing.price, layout.data.symbol)}`, color, 'right');
        } else if (drawing.type === 'vline') {
            const xx = layout.x(domainPointBarIndex(drawing, layout));
            ctx.setLineDash([8, 5]);
            ctx.beginPath();
            ctx.moveTo(xx, layout.priceArea.top);
            ctx.lineTo(xx, layout.panelBottom);
            ctx.stroke();
            const bar = layout.allBars[Math.round(drawing.barIndex)];
            drawDrawingTag(ctx, xx + 4, layout.priceArea.top + 14, drawing.label || formatChartTime(bar?.time_iso || bar?.time), color, 'left');
        } else if (drawing.type === 'cross-line') {
            const xx = layout.x(domainPointBarIndex(drawing, layout));
            const yy = layout.y(drawing.price);
            ctx.setLineDash([5, 4]);
            ctx.beginPath();
            ctx.moveTo(layout.priceArea.left, yy);
            ctx.lineTo(layout.priceArea.right, yy);
            ctx.moveTo(xx, layout.priceArea.top);
            ctx.lineTo(xx, layout.panelBottom);
            ctx.stroke();
        } else if (drawing.type === 'trendline' && drawing.points?.length >= 2) {
            drawLineLikeDrawing(ctx, layout, drawing, color);
        } else if ((drawing.type === 'ray' || drawing.type === 'extended-line' || (LINE_LIKE_TOOLS.has(drawing.type) && !drawing.type.startsWith('fib') && !drawing.type.startsWith('gann') && drawing.type !== 'measure')) && drawing.points?.length >= 2) {
            drawLineLikeDrawing(ctx, layout, drawing, color);
        } else if (CHANNEL_TOOLS.has(drawing.type) && drawing.points?.length >= 2) {
            drawChannelDrawing(ctx, layout, drawing, color);
        } else if (BOX_TOOLS.has(drawing.type) && drawing.points?.length >= 2) {
            drawBoxDrawing(ctx, layout, drawing, color, selected);
        } else if (drawing.type === 'fib' && drawing.points?.length >= 2) {
            drawFibDrawing(ctx, layout, drawing, color);
        } else if (drawing.type?.startsWith('fib-') && drawing.points?.length >= 2) {
            drawFibVariantDrawing(ctx, layout, drawing, color);
        } else if (drawing.type?.startsWith('gann-') && drawing.points?.length >= 2) {
            drawGannDrawing(ctx, layout, drawing, color);
        } else if (drawing.type === 'measure' && drawing.points?.length >= 2) {
            drawMeasureDrawing(ctx, layout, drawing, color);
        } else if (MULTI_POINT_TOOLS.has(drawing.type)) {
            drawMultiPointDrawing(ctx, layout, drawing, color);
        } else if (TEXT_TOOLS.has(drawing.type) || drawing.type === 'price-label') {
            drawTextDrawing(ctx, layout, drawing, selected);
        } else if (['dot', 'pin', 'flag', 'icon', 'arrow-marker', 'up-arrow', 'down-arrow'].includes(drawing.type)) {
            drawPointIconDrawing(ctx, layout, drawing, color);
        } else if (drawing.type === 'position') {
            drawPositionDrawing(ctx, layout, drawing, selected);
        }
        if (selected && drawing.type !== 'position') {
            const isResizableBox = BOX_TOOLS.has(drawing.type) && drawing.points?.length >= 2;
            if (isResizableBox) {
                drawBoxResizeHandles(ctx, layout, drawing);
            } else {
                drawDrawingAnchors(ctx, layout, drawing);
            }
        }
        ctx.restore();
    }

    function rayEndPoint(a, b, layout) {
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        if (Math.abs(dx) < 0.0001) {
            return { x: b.x, y: dy >= 0 ? layout.panelBottom : layout.priceArea.top };
        }
        const targetX = dx >= 0 ? layout.priceArea.right : layout.priceArea.left;
        const t = (targetX - a.x) / dx;
        return {
            x: targetX,
            y: a.y + dy * t,
        };
    }

    function applyDrawingLineStyle(ctx, drawing) {
        if (drawing.lineStyle === 'dashed') ctx.setLineDash([8, 5]);
        if (drawing.lineStyle === 'dotted') ctx.setLineDash([2, 5]);
    }

    function drawingPointsWithPreview(drawing) {
        const points = Array.isArray(drawing.points) ? drawing.points.slice() : [];
        if (drawing.previewPoint) points.push(drawing.previewPoint);
        return points;
    }

    function drawLineLikeDrawing(ctx, layout, drawing, color) {
        const points = drawingPointsWithPreview(drawing);
        if (points.length < 2) return;
        const a = screenPointFromDomain(points[0], layout);
        const b = screenPointFromDomain(points[1], layout);
        let start = a;
        let end = b;
        if (drawing.type === 'ray' || drawing.type === 'forecast' || drawing.type === 'anchored-vwap') {
            end = rayEndPoint(a, b, layout);
        } else if (drawing.type === 'extended-line') {
            start = rayEndPoint(b, a, layout);
            end = rayEndPoint(a, b, layout);
        }
        ctx.beginPath();
        ctx.moveTo(start.x, start.y);
        ctx.lineTo(end.x, end.y);
        ctx.stroke();
        if (drawing.type === 'trend-angle') {
            const degrees = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
            drawDrawingTag(ctx, b.x + 6, b.y, `${Math.abs(degrees).toFixed(1)}°`, color, 'left');
        }
        if (drawing.type === 'arrow' || drawing.type === 'arrow-marker' || drawing.type === 'forecast') {
            drawArrowHead(ctx, a, b, color);
        }
        if (drawing.type === 'anchored-vwap') {
            drawDrawingTag(ctx, a.x + 6, a.y, 'Anchored VWAP', color, 'left');
        }
    }

    function drawChannelDrawing(ctx, layout, drawing, color) {
        const points = drawingPointsWithPreview(drawing);
        if (points.length < 2) return;
        const a = screenPointFromDomain(points[0], layout);
        const b = screenPointFromDomain(points[1], layout);
        const offset = points[2] ? screenPointFromDomain(points[2], layout) : { x: a.x, y: a.y - 44 };
        const dx = offset.x - a.x;
        const dy = offset.y - a.y;
        ctx.beginPath();
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
        ctx.moveTo(a.x + dx, a.y + dy);
        ctx.lineTo(b.x + dx, b.y + dy);
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(a.x + dx, a.y + dy);
        ctx.moveTo(b.x, b.y);
        ctx.lineTo(b.x + dx, b.y + dy);
        ctx.stroke();
        ctx.save();
        ctx.fillStyle = colorWithAlpha(color, Number(drawing.opacity ?? 10) / 100);
        ctx.beginPath();
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
        ctx.lineTo(b.x + dx, b.y + dy);
        ctx.lineTo(a.x + dx, a.y + dy);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function drawBoxDrawing(ctx, layout, drawing, color) {
        const points = drawingPointsWithPreview(drawing);
        if (points.length < 2) return;
        const a = screenPointFromDomain(points[0], layout);
        const b = screenPointFromDomain(points[1], layout);
        const left = Math.min(a.x, b.x);
        const top = Math.min(a.y, b.y);
        const width = Math.abs(b.x - a.x);
        const height = Math.abs(b.y - a.y);
        ctx.save();
        ctx.strokeStyle = color;
        ctx.fillStyle = colorWithAlpha(color, Number(drawing.opacity ?? 14) / 100);
        if (drawing.type === 'circle' || drawing.type === 'ellipse' || drawing.type === 'fib-circle') {
            ctx.beginPath();
            ctx.ellipse(left + width / 2, top + height / 2, Math.max(3, width / 2), Math.max(3, height / 2), 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        } else if (drawing.type === 'triangle-shape' || drawing.type === 'fib-wedge') {
            ctx.beginPath();
            ctx.moveTo(left + width / 2, top);
            ctx.lineTo(left + width, top + height);
            ctx.lineTo(left, top + height);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();
        } else if (drawing.type === 'volume-profile') {
            const rows = 8;
            for (let i = 0; i < rows; i++) {
                const rowH = height / rows;
                const rowW = width * (0.35 + 0.55 * Math.abs(Math.sin(i * 1.7)));
                ctx.fillRect(left, top + i * rowH, rowW, Math.max(1, rowH - 2));
            }
            ctx.strokeRect(left, top, width, height);
        } else if (drawing.type === 'price-range' || drawing.type === 'date-range' || drawing.type === 'date-price-range') {
            ctx.fillRect(left, top, width, height);
            ctx.strokeRect(left, top, width, height);
            const label = drawing.type === 'price-range'
                ? `${formatSigned((points[1].price - points[0].price) / pipSizeForSymbol(layout.data.symbol), 1)} pip`
                : drawing.type === 'date-range'
                  ? `${Math.round(Math.abs(points[1].barIndex - points[0].barIndex))} nến`
                  : `${Math.round(Math.abs(points[1].barIndex - points[0].barIndex))} nến / ${formatSigned((points[1].price - points[0].price) / pipSizeForSymbol(layout.data.symbol), 1)} pip`;
            drawDrawingTag(ctx, left + width + 6, top + 12, label, color, 'left');
        } else {
            ctx.fillRect(left, top, width, height);
            ctx.strokeRect(left, top, width, height);
            if (drawing.type === 'rect' && drawing.zoneType) {
                drawDrawingTag(ctx, left + 6, top + 14, drawing.label || drawing.zoneType.toUpperCase(), color, 'left');
            }
        }
        ctx.restore();
    }

    function drawMultiPointDrawing(ctx, layout, drawing, color) {
        const points = drawingPointsWithPreview(drawing);
        if (!points.length) return;
        const screen = points.map((point) => screenPointFromDomain(point, layout));
        ctx.beginPath();
        screen.forEach((point, index) => {
            if (index === 0) ctx.moveTo(point.x, point.y);
            else ctx.lineTo(point.x, point.y);
        });
        if (drawing.type === 'triangle-pattern' || drawing.type === 'head-shoulders') ctx.closePath();
        ctx.stroke();
        if (drawing.type === 'brush' || drawing.type === 'highlighter') {
            ctx.save();
            ctx.strokeStyle = colorWithAlpha(color, drawing.type === 'highlighter' ? 0.36 : 0.8);
            ctx.lineWidth = drawing.type === 'highlighter' ? 8 : Number(drawing.width || 3);
            ctx.stroke();
            ctx.restore();
        }
        screen.forEach((point, index) => {
            if (['xabcd', 'abcd', 'elliott-wave', 'three-drives'].includes(drawing.type)) {
                const labels = drawing.type === 'xabcd' ? ['X', 'A', 'B', 'C', 'D'] : drawing.type === 'abcd' ? ['A', 'B', 'C', 'D'] : ['1', '2', '3', '4', '5'];
                drawDrawingTag(ctx, point.x + 4, point.y - 8, labels[index] || String(index + 1), color, 'left');
            }
        });
        if (drawing.type.includes('pitchfork') && screen.length >= 3) {
            const [a, b, c] = screen;
            const mid = { x: (b.x + c.x) / 2, y: (b.y + c.y) / 2 };
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(mid.x, mid.y);
            ctx.moveTo(b.x, b.y);
            ctx.lineTo(mid.x + (mid.x - a.x), mid.y + (mid.y - a.y));
            ctx.moveTo(c.x, c.y);
            ctx.lineTo(mid.x + (mid.x - a.x), mid.y + (mid.y - a.y));
            ctx.stroke();
        }
        if (drawing.type === 'cyclic-lines' && points.length >= 2) {
            const step = Math.max(1, Math.abs(points[1].barIndex - points[0].barIndex));
            for (let barIndex = points[0].barIndex; barIndex < layout.lastExclusive; barIndex += step) {
                const xx = layout.x(barIndex);
                ctx.beginPath();
                ctx.moveTo(xx, layout.priceArea.top);
                ctx.lineTo(xx, layout.panelBottom);
                ctx.stroke();
            }
        }
    }

    function drawFibVariantDrawing(ctx, layout, drawing, color) {
        if (drawing.type === 'fib-time-zone') {
            const points = drawingPointsWithPreview(drawing);
            const a = points[0];
            const b = points[1];
            const step = Math.max(1, Math.abs(b.barIndex - a.barIndex));
            FIB_LEVELS.concat([1.618, 2.618]).forEach((level) => {
                const xx = layout.x(a.barIndex + step * level);
                ctx.beginPath();
                ctx.moveTo(xx, layout.priceArea.top);
                ctx.lineTo(xx, layout.panelBottom);
                ctx.stroke();
                drawDrawingTag(ctx, xx + 4, layout.priceArea.top + 14, String(level), color, 'left');
            });
            return;
        }
        if (drawing.type === 'fib-fan') {
            const points = drawingPointsWithPreview(drawing);
            const a = screenPointFromDomain(points[0], layout);
            const b = screenPointFromDomain(points[1], layout);
            FIB_LEVELS.slice(1).forEach((level) => {
                const target = { x: b.x, y: a.y + (b.y - a.y) * level };
                const end = rayEndPoint(a, target, layout);
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(end.x, end.y);
                ctx.stroke();
            });
            return;
        }
        drawFibDrawing(ctx, layout, drawing, color);
    }

    function drawGannDrawing(ctx, layout, drawing, color) {
        if (drawing.type === 'gann-square' || drawing.type === 'gann-box') {
            drawBoxDrawing(ctx, layout, drawing, color);
            return;
        }
        const points = drawingPointsWithPreview(drawing);
        if (points.length < 2) return;
        const a = screenPointFromDomain(points[0], layout);
        const b = screenPointFromDomain(points[1], layout);
        [-2, -1, -0.5, 0, 0.5, 1, 2].forEach((slope) => {
            const target = { x: b.x, y: a.y + (b.y - a.y) * (1 + slope * 0.25) };
            const end = rayEndPoint(a, target, layout);
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(end.x, end.y);
            ctx.stroke();
        });
    }

    function drawPointIconDrawing(ctx, layout, drawing, color) {
        const point = screenPointFromDomain(drawing.point || drawing.points?.[0] || { barIndex: 0, price: layout.hi }, layout);
        const labels = {
            dot: '•',
            pin: '⌖',
            flag: '⚑',
            icon: '★',
            'arrow-marker': '➤',
            'up-arrow': '↑',
            'down-arrow': '↓',
        };
        ctx.save();
        ctx.fillStyle = color;
        ctx.font = `700 ${Number(drawing.textSize || 18)}px Segoe UI, Arial`;
        ctx.fillText(labels[drawing.type] || '•', point.x - 6, point.y + 6);
        ctx.restore();
    }

    function drawArrowHead(ctx, from, to, color) {
        const angle = Math.atan2(to.y - from.y, to.x - from.x);
        const size = 9;
        ctx.save();
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.moveTo(to.x, to.y);
        ctx.lineTo(to.x - Math.cos(angle - Math.PI / 6) * size, to.y - Math.sin(angle - Math.PI / 6) * size);
        ctx.lineTo(to.x - Math.cos(angle + Math.PI / 6) * size, to.y - Math.sin(angle + Math.PI / 6) * size);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function colorWithAlpha(color, alpha) {
        const value = String(color || '#1f6feb').replace('#', '');
        const full = value.length === 3 ? value.split('').map((c) => c + c).join('') : value.padEnd(6, '0').slice(0, 6);
        const r = parseInt(full.slice(0, 2), 16);
        const g = parseInt(full.slice(2, 4), 16);
        const b = parseInt(full.slice(4, 6), 16);
        return `rgba(${r},${g},${b},${clamp(alpha, 0, 1)})`;
    }

    function drawFibDrawing(ctx, layout, drawing, color) {
        const a = drawing.points[0];
        const b = drawing.points[1];
        const x1 = layout.x(a.barIndex);
        const x2 = layout.x(b.barIndex);
        const left = clamp(Math.min(x1, x2), layout.priceArea.left, layout.priceArea.right);
        const right = layout.priceArea.right;
        ctx.save();
        ctx.font = '700 11px Segoe UI, Arial';
        FIB_LEVELS.forEach((level) => {
            const price = a.price + (b.price - a.price) * level;
            const yy = layout.y(price);
            ctx.strokeStyle = color;
            ctx.globalAlpha = level === 0 || level === 1 ? 0.9 : 0.62;
            ctx.setLineDash(level === 0 || level === 1 ? [] : [5, 4]);
            ctx.beginPath();
            ctx.moveTo(left, yy);
            ctx.lineTo(right, yy);
            ctx.stroke();
            ctx.globalAlpha = 1;
            drawDrawingTag(ctx, right - 6, yy, `${formatFixed(level * 100, 1)}% ${formatPrice(price, layout.data.symbol)}`, color, 'right');
        });
        ctx.restore();
    }

    function drawMeasureDrawing(ctx, layout, drawing, color) {
        const a = screenPointFromDomain(drawing.points[0], layout);
        const b = screenPointFromDomain(drawing.points[1], layout);
        const barDelta = Math.round(Math.abs(drawing.points[1].barIndex - drawing.points[0].barIndex));
        const priceDelta = drawing.points[1].price - drawing.points[0].price;
        const percent = drawing.points[0].price ? (priceDelta / drawing.points[0].price) * 100 : 0;
        const pips = priceDelta / pipSizeForSymbol(layout.data.symbol);
        const label = `${barDelta} nến | ${formatSigned(pips, 1)} pip | ${formatSigned(percent, 2)}%`;
        ctx.save();
        ctx.strokeStyle = color;
        ctx.fillStyle = 'rgba(31,111,235,0.08)';
        ctx.setLineDash([5, 4]);
        ctx.strokeRect(Math.min(a.x, b.x), Math.min(a.y, b.y), Math.abs(b.x - a.x), Math.abs(b.y - a.y));
        ctx.setLineDash([]);
        ctx.beginPath();
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
        ctx.stroke();
        ctx.fillRect(Math.min(a.x, b.x), Math.min(a.y, b.y), Math.abs(b.x - a.x), Math.abs(b.y - a.y));
        drawDrawingTag(ctx, b.x + 6, b.y, label, color, 'left');
        ctx.restore();
    }

    function drawTextDrawing(ctx, layout, drawing, selected) {
        const point = screenPointFromDomain(drawing.point || drawing.points?.[0] || { barIndex: 0, price: layout.hi }, layout);
        const text = String(drawing.text || drawing.label || 'Ghi chú').slice(0, 80);
        ctx.save();
        ctx.font = `700 ${Number(drawing.textSize || 12)}px Segoe UI, Arial`;
        const width = Math.min(260, Math.max(52, ctx.measureText(text).width + 16));
        const height = 26;
        ctx.fillStyle = drawing.type === 'price-label' ? (drawing.color || '#1f6feb') : (bodyIsDark() ? 'rgba(17,25,35,0.92)' : 'rgba(255,255,255,0.94)');
        ctx.strokeStyle = selected ? '#0f766e' : drawing.color || '#1f6feb';
        ctx.lineWidth = selected ? 2 : 1.2;
        ctx.fillRect(point.x, point.y - height, width, height);
        ctx.strokeRect(point.x, point.y - height, width, height);
        ctx.fillStyle = drawing.type === 'price-label' ? '#ffffff' : (drawing.color || '#1f2937');
        ctx.fillText(text, point.x + 8, point.y - 8, width - 16);
        ctx.restore();
    }

    function drawDrawingTag(ctx, x, y, text, color, align = 'left') {
        const label = String(text || '').slice(0, 64);
        if (!label) return;
        ctx.save();
        ctx.font = '700 11px Segoe UI, Arial';
        const width = Math.max(42, ctx.measureText(label).width + 12);
        const height = 20;
        const left = align === 'right' ? x - width : x;
        ctx.fillStyle = color;
        ctx.fillRect(left, y - height / 2, width, height);
        ctx.fillStyle = '#ffffff';
        ctx.fillText(label, left + 6, y + 4);
        ctx.restore();
    }

    function drawPositionDrawing(ctx, layout, drawing, selected = false) {
        const bounds = positionScreenBounds(drawing, layout);
        if (!bounds) return;

        const entryY = layout.y(drawing.entry);
        const stopY = layout.y(drawing.stop);
        const targetY = layout.y(drawing.target);
        const profitTop = Math.min(entryY, targetY);
        const profitBottom = Math.max(entryY, targetY);
        const riskTop = Math.min(entryY, stopY);
        const riskBottom = Math.max(entryY, stopY);
        const isLong = drawing.direction === 'long';
        const rr = positionRewardRisk(drawing);

        ctx.save();
        ctx.fillStyle = 'rgba(8, 153, 129, 0.18)';
        ctx.fillRect(bounds.left, profitTop, bounds.right - bounds.left, profitBottom - profitTop);
        ctx.fillStyle = 'rgba(242, 54, 69, 0.18)';
        ctx.fillRect(bounds.left, riskTop, bounds.right - bounds.left, riskBottom - riskTop);

        ctx.strokeStyle = selected ? '#0f766e' : '#4b5563';
        ctx.lineWidth = selected ? 2.5 : 1.5;
        ctx.strokeRect(bounds.left, Math.min(profitTop, riskTop), bounds.right - bounds.left, Math.max(profitBottom, riskBottom) - Math.min(profitTop, riskTop));

        ctx.setLineDash([5, 4]);
        drawPositionLine(ctx, bounds.left, bounds.right, targetY, '#089981');
        drawPositionLine(ctx, bounds.left, bounds.right, entryY, '#1f2937');
        drawPositionLine(ctx, bounds.left, bounds.right, stopY, '#f23645');
        ctx.setLineDash([]);

        ctx.font = '700 12px Segoe UI, Arial';
        drawPositionTag(ctx, bounds.right, targetY, `TP ${formatPrice(drawing.target, layout.data.symbol)}`, '#089981');
        drawPositionTag(ctx, bounds.right, entryY, `Vào ${formatPrice(drawing.entry, layout.data.symbol)}`, '#1f2937');
        drawPositionTag(ctx, bounds.right, stopY, `SL ${formatPrice(drawing.stop, layout.data.symbol)}`, '#f23645');

        ctx.fillStyle = isLong ? '#047857' : '#b91c1c';
        ctx.font = '700 12px Segoe UI, Arial';
        ctx.fillText(`${isLong ? 'MUA' : 'BÁN'} | RR 1:${formatFixed(rr, 2)}`, bounds.left + 8, Math.min(profitTop, riskTop) + 18);
        if (selected) {
            drawPositionHandles(ctx, layout, drawing);
        }
        ctx.restore();
    }

    function drawPositionLine(ctx, x1, x2, y, color) {
        ctx.strokeStyle = color;
        ctx.beginPath();
        ctx.moveTo(x1, y);
        ctx.lineTo(x2, y);
        ctx.stroke();
    }

    function drawPositionTag(ctx, x, y, text, color) {
        const paddingX = 6;
        const height = 22;
        const width = Math.max(68, ctx.measureText(text).width + paddingX * 2);
        ctx.fillStyle = color;
        ctx.fillRect(x - width, y - height / 2, width, height);
        ctx.fillStyle = '#ffffff';
        ctx.fillText(text, x - width + paddingX, y + 4);
    }

    function drawPositionHandles(ctx, layout, drawing) {
        const size = 8;
        ctx.save();
        ctx.lineWidth = 2;
        positionHandlePoints(drawing, layout).forEach((handle) => {
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#1f6feb';
            ctx.fillRect(handle.x - size / 2, handle.y - size / 2, size, size);
            ctx.strokeRect(handle.x - size / 2, handle.y - size / 2, size, size);
        });
        ctx.restore();
    }

    function drawSelectionBox(ctx, layout) {
        const box = state.chartView.selectionBox;
        if (!box) return;
        const a = screenPointFromDomain(box.start, layout);
        const b = screenPointFromDomain(box.end, layout);
        const left = Math.min(a.x, b.x);
        const top = Math.min(a.y, b.y);
        const width = Math.abs(a.x - b.x);
        const height = Math.abs(a.y - b.y);
        const crossing = b.x < a.x;
        ctx.save();
        ctx.fillStyle = crossing ? 'rgba(15,118,110,0.10)' : 'rgba(31,111,235,0.08)';
        ctx.strokeStyle = crossing ? '#0f766e' : '#1f6feb';
        ctx.setLineDash([5, 4]);
        ctx.fillRect(left, top, width, height);
        ctx.strokeRect(left, top, width, height);
        ctx.restore();
    }

    function drawDrawingSelectionOutline(ctx, layout, drawing) {
        const bounds = drawingScreenBounds(drawing, layout);
        if (!bounds) return;
        ctx.save();
        ctx.strokeStyle = '#0f766e';
        ctx.setLineDash([4, 3]);
        ctx.lineWidth = 1.5;
        ctx.strokeRect(bounds.left - 5, bounds.top - 5, bounds.right - bounds.left + 10, bounds.bottom - bounds.top + 10);
        ctx.restore();
    }

    function drawDrawingAnchors(ctx, layout, drawing) {
        const anchors = drawingAnchorPoints(drawing, layout);
        if (!anchors.length) return;
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.strokeStyle = '#1f6feb';
        ctx.lineWidth = 1.6;
        anchors.forEach((anchor) => {
            ctx.beginPath();
            ctx.rect(anchor.x - 4, anchor.y - 4, 8, 8);
            ctx.fill();
            ctx.stroke();
        });
        ctx.restore();
    }

    function drawBoxResizeHandles(ctx, layout, drawing) {
        const handles = boxDrawingResizeHandles(drawing, layout);
        if (!handles.length) return;
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.strokeStyle = '#0f766e';
        ctx.lineWidth = 1.8;
        handles.forEach((handle) => {
            ctx.beginPath();
            ctx.rect(handle.x - 4.5, handle.y - 4.5, 9, 9);
            ctx.fill();
            ctx.stroke();
        });
        ctx.restore();
    }

    function resetChartView(clearDrawings = false) {
        const bars = state.activeMarketData?.bars || [];
        state.chartView.barsOnScreen = Math.min(180, Math.max(40, bars.length || 180));
        state.chartView.rightOffset = 0;
        state.chartView.panPixelOffset = 0;
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        state.chartView.draftDrawing = null;
        state.chartView.selectedDrawingIds = [];
        state.chartView.selectionBox = null;
        state.chartView.crosshairPoint = null;
        state.chartView.quickMenu = null;
        state.chartView.drag = null;
        hideChartQuickMenu();
        if (clearDrawings && state.activeMarketData) {
            pushDrawingHistory();
            state.chartView.drawings[chartDrawingKey(state.activeMarketData)] = [];
            persistChartDrawings();
        }
    }

    function chartRightOffsetLimits(totalBars, barsOnScreen) {
        const minBars = Math.min(12, totalBars);
        const futureBars = chartFutureOffsetLimit(totalBars, barsOnScreen || minBars);
        return {
            min: -futureBars,
            max: Math.max(0, totalBars - minBars),
        };
    }

    function chartFutureOffsetLimit(totalBars, barsOnScreen) {
        if (!totalBars) return 0;
        const span = Math.max(1, Math.round(barsOnScreen || 1));
        return Math.min(10000, Math.max(500, span * 8, Math.min(totalBars, 10000)));
    }

    function chartDataKey(data) {
        return `${data?.symbol || 'UNKNOWN'}_${data?.timeframe || 'TF'}`;
    }

    function chartDrawingKey(data) {
        const symbol = String(data?.symbol || '').trim().toUpperCase();
        return symbol ? `${symbol}_SHARED_DRAWINGS` : chartDataKey(data);
    }

    function getChartDrawings(data) {
        const key = chartDrawingKey(data);
        if (!Array.isArray(state.chartView.drawings[key])) {
            state.chartView.drawings[key] = [];
        }
        const legacyKey = chartDataKey(data);
        if (legacyKey !== key && Array.isArray(state.chartView.drawings[legacyKey]) && state.chartView.drawings[legacyKey].length) {
            const known = new Set(state.chartView.drawings[key].map((drawing) => getDrawingId(drawing)));
            state.chartView.drawings[legacyKey].forEach((drawing) => {
                const id = getDrawingId(drawing);
                if (!known.has(id)) {
                    state.chartView.drawings[key].push(drawing);
                    known.add(id);
                }
            });
            state.chartView.drawings[legacyKey] = [];
            persistChartDrawings();
        }
        state.chartView.drawings[key].forEach((drawing) => getDrawingId(drawing));
        return state.chartView.drawings[key];
    }
    function setChartTool(tool) {
        hideChartQuickMenu();
        if (tool !== 'rect') state.chartView.pendingZoneType = null;
        state.chartView.activeTool = tool;
        state.chartView.draftDrawing = null;
        state.chartView.selectionBox = null;
        $$('[data-chart-tool]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.chartTool === tool);
        });
        $$('[data-chart-zone]').forEach((button) => {
            button.classList.toggle('is-active', tool === 'rect' && button.dataset.chartZone === state.chartView.pendingZoneType);
        });
        updateChartBottomStatus();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function setChartZone(zoneType) {
        state.chartView.pendingZoneType = zoneType === 'supply' ? 'supply' : 'demand';
        setChartTool('rect');
    }

    function returnToCursorAfterDrawing() {
        if (!state.chartView.stayInDrawingMode) {
            setChartTool('cursor');
        } else {
            updateChartBottomStatus();
        }
    }

    function cancelActiveDrawingTool() {
        state.chartView.draftDrawing = null;
        state.chartView.selectionBox = null;
        hideChartQuickMenu();
        setChartTool('cursor');
    }

    async function changeChartTimeframe(timeframe) {
        state.chartView.timeframe = timeframe;
        updateChartTimeframeButtons();
        renderSymbolWatchlist();
        const symbol = state.activeMarketData?.symbol || chartSymbols()[0];
        if (!symbol) return;
        const file = findMarketFile(symbol, timeframe);
        if (!file) {
            showToast(`${symbol} ${timeframe} chưa có dữ liệu.`);
            return;
        }
        await loadMarketData(file.file);
    }

    function toggleChartFullscreen() {
        const panel = $('#marketChartPanel');
        const button = $('#chartFullscreenBtn');
        if (state.activeTab === 'pa-training') {
            const paPanel = $('#tab-pa-training');
            if (!paPanel || !panel) return;
            panel.classList.remove('is-fullscreen');
            paPanel.classList.toggle('is-pa-fullscreen');
            setChartButtonLabel(button, paPanel.classList.contains('is-pa-fullscreen') ? 'Thu gọn' : 'Phóng to');
        } else {
            $('#tab-pa-training')?.classList.remove('is-pa-fullscreen');
            if (!panel) return;
            panel.classList.toggle('is-fullscreen');
            setChartButtonLabel(button, panel.classList.contains('is-fullscreen') ? 'Thu gọn' : 'Phóng to');
        }
        setTimeout(() => {
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        }, 60);
    }

    function setChartButtonLabel(button, label, compactLabel = null) {
        if (!button) return;
        const full = button.querySelector('.chart-label-full');
        const compact = button.querySelector('.chart-label-compact');
        if (!full || !compact) {
            button.textContent = label;
            return;
        }
        full.textContent = label;
        if (compactLabel) compact.textContent = compactLabel;
    }

    function bindChartEvents() {
        const canvas = $('#marketChart');
        canvas.addEventListener('wheel', handleChartWheel, { passive: false });
        canvas.addEventListener('mousedown', handleChartMouseDown);
        canvas.addEventListener('mousemove', handleChartMouseMove);
        canvas.addEventListener('dblclick', handleChartDoubleClick);
        canvas.addEventListener('mouseleave', handleChartMouseLeave);
        canvas.addEventListener('contextmenu', handleChartContextMenu);
        window.addEventListener('mouseup', handleChartMouseUp);
        window.addEventListener('keydown', handleChartKeyDown);

        $$('[data-chart-tool]').forEach((button) => {
            button.addEventListener('click', () => setChartTool(button.dataset.chartTool));
        });
        $$('[data-chart-zone]').forEach((button) => {
            button.addEventListener('click', () => setChartZone(button.dataset.chartZone));
        });
        $('#chartSymbolSelect')?.addEventListener('change', (event) => {
            openWatchSymbol(event.target.value).catch((e) => showToast(e.message));
        });
        $('#chartAddCompareBtn')?.addEventListener('click', () => addCompareSymbol().catch((e) => showToast(e.message)));
        $('#chartClearCompareBtn')?.addEventListener('click', clearCompareSymbols);
        $('#chartSelectionToM15Btn')?.addEventListener('click', () => jumpToSelectedChartRange('M15').catch((e) => showToast(e.message)));
        $('#chartSelectionToH1Btn')?.addEventListener('click', () => jumpToSelectedChartRange('H1').catch((e) => showToast(e.message)));
        $('#chartTimeframeMoreToggle')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const wrapper = $('#chartTimeframeMore');
            setChartTimeframeMenuOpen(!wrapper?.classList.contains('is-open'));
        });
        document.addEventListener('click', (event) => {
            const wrapper = $('#chartTimeframeMore');
            if (wrapper?.classList.contains('is-open') && !wrapper.contains(event.target)) {
                setChartTimeframeMenuOpen(false);
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setChartTimeframeMenuOpen(false);
        });
        window.addEventListener('resize', positionChartTimeframeMenu);
        document.addEventListener('scroll', () => setChartTimeframeMenuOpen(false), true);
        $$('[data-chart-timeframe]').forEach((button) => {
            button.addEventListener('click', () => {
                setChartTimeframeMenuOpen(false);
                changeChartTimeframe(button.dataset.chartTimeframe).catch((e) => showToast(e.message));
            });
        });
        $('#chartTypeSelect')?.addEventListener('change', (event) => {
            state.chartView.chartType = event.target.value || 'candles';
            persistChartSettings();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartMagnetMode')?.addEventListener('change', (event) => {
            state.chartView.magnetMode = event.target.value || 'off';
            state.chartView.magnetModeExplicit = true;
            state.chartView.snapToOhlc = state.chartView.magnetMode !== 'off';
            persistChartSettings();
            updateChartBottomStatus();
        });
        $('#chartStayDrawingToggle')?.addEventListener('change', (event) => {
            state.chartView.stayInDrawingMode = event.target.checked;
            persistChartSettings();
        });
        $('#chartLockToggle')?.addEventListener('change', (event) => {
            state.chartView.drawingsLocked = event.target.checked;
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartHideDrawingsToggle')?.addEventListener('change', (event) => {
            state.chartView.drawingsVisible = !event.target.checked;
            state.chartView.selectedDrawingIds = [];
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartFavoritesToggle')?.addEventListener('change', (event) => {
            state.chartView.favoritesVisible = event.target.checked;
            persistChartSettings();
            renderFavoriteToolbar();
        });
        $('#chartUndoBtn')?.addEventListener('click', undoChartDrawing);
        $('#chartRedoBtn')?.addEventListener('click', redoChartDrawing);
        $('#chartExportDrawingsBtn')?.addEventListener('click', exportChartDrawings);
        $('#chartImportDrawingsBtn')?.addEventListener('click', () => $('#chartDrawingImport')?.click());
        $('#chartDrawingImport')?.addEventListener('change', importChartDrawings);
        $('#chartIndicatorTemplate')?.addEventListener('change', (event) => applyIndicatorTemplate(event.target.value));
        $('#chartSaveIndicatorTemplateBtn')?.addEventListener('click', saveCurrentIndicatorTemplate);
        $('#chartAlertBtn')?.addEventListener('click', createAlertAtLastPrice);
        $('#chartSaveLayoutBtn')?.addEventListener('click', saveChartLayout);
        $('#chartLoadLayoutBtn')?.addEventListener('click', loadSelectedChartLayout);
        $('#chartMultiLayoutBtn')?.addEventListener('click', cycleChartLayoutSlots);
        $('#chartQuickSearchBtn')?.addEventListener('click', quickSearchChart);
        $('#chartQuickSearchInput')?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') quickSearchChart();
        });
        $('#chartSettingsBtn')?.addEventListener('click', openChartSettings);
        $('#chartResetBtn')?.addEventListener('click', () => {
            resetChartView(false);
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartFullscreenBtn')?.addEventListener('click', toggleChartFullscreen);
        $('#chartClearDrawingsBtn')?.addEventListener('click', () => {
            resetChartView(true);
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartFitBtn')?.addEventListener('click', fitFullChart);
        $('#chartFitHorizontalBtn')?.addEventListener('click', fitChartHorizontally);
        $('#chartFitVerticalBtn')?.addEventListener('click', fitChartVertically);
        $('#chartJumpStartBtn')?.addEventListener('click', () => {
            stopChartReplay(false);
            state.chartView.replay.enabled = false;
            state.chartView.replay.index = 0;
            if (state.activeTab === 'pa-training') persistPaWorkspace({ replayIndex: 0, replayEnabled: false });
            jumpChartToBoundary('start');
        });
        $('#chartJumpEndBtn')?.addEventListener('click', () => {
            const last = Math.max(0, (state.activeMarketData?.bars || []).length - 1);
            stopChartReplay(false);
            state.chartView.replay.enabled = false;
            state.chartView.replay.index = last;
            if (state.activeTab === 'pa-training') persistPaWorkspace({ replayIndex: last, replayEnabled: false });
            jumpChartToBoundary('end');
        });
        $('#chartScreenshotBtn')?.addEventListener('click', takeChartScreenshot);
        $('#chartThemeBtn')?.addEventListener('click', toggleChartTheme);
        $('#chartZoomBoxBtn')?.addEventListener('click', () => setChartTool('zoom-box'));
        $('#chartPrevTradeBtn')?.addEventListener('click', previousTrade);
        $('#chartNextTradeBtn')?.addEventListener('click', nextTradeSelection);
        $('#chartRunSelect')?.addEventListener('change', (event) => {
            state.chartView.activeRunId = event.target.value;
            state.chartView.selectedTradeId = null;
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartTradeFilter')?.addEventListener('change', (event) => {
            state.chartView.tradeFilter = event.target.value;
            state.chartView.selectedTradeId = null;
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $$('[data-chart-toggle]').forEach((input) => {
            input.addEventListener('change', () => {
                const key = input.dataset.chartToggle;
                if (key in state.chartView.indicators) state.chartView.indicators[key] = input.checked;
                if (key in state.chartView.overlays) state.chartView.overlays[key] = input.checked;
                persistChartSettings();
                if (state.activeMarketData) drawMarketChart(state.activeMarketData);
            });
        });
        $('#chartReplayBackBtn')?.addEventListener('click', () => {
            const current = state.chartView.replay.enabled ? state.chartView.replay.index : state.chartView.lastLayout?.first || 0;
            stopChartReplay(false);
            setChartReplayIndex(current - Math.max(1, Math.round(state.chartView.replay.speed || 1)), true);
        });
        $('#chartReplayPlayBtn')?.addEventListener('click', toggleChartReplay);
        $('#chartReplayStepBtn')?.addEventListener('click', () => {
            const current = state.chartView.replay.enabled ? state.chartView.replay.index : state.chartView.lastLayout?.first || 0;
            stopChartReplay(false);
            setChartReplayIndex(current + Math.max(1, Math.round(state.chartView.replay.speed || 1)), true);
        });
        $('#chartReplaySlowerBtn')?.addEventListener('click', () => changeChartReplaySpeed(-1));
        $('#chartReplayFasterBtn')?.addEventListener('click', () => changeChartReplaySpeed(1));
        $('#chartReplayRange')?.addEventListener('input', (event) => {
            stopChartReplay(false);
            setChartReplayIndex(Number(event.target.value), true);
        });
        $('#chartReplaySpeed')?.addEventListener('change', (event) => {
            state.chartView.replay.speed = Number(event.target.value) || 1;
            if (state.chartView.replay.playing) {
                stopChartReplay(false);
                toggleChartReplay();
            }
        });
        $$('[data-range-preset]').forEach((button) => {
            button.addEventListener('click', () => applyRangePreset(button.dataset.rangePreset));
        });
        $('#chartGoToDateBtn')?.addEventListener('click', goToChartDate);
        $('#chartTimezoneSelect')?.addEventListener('change', (event) => {
            state.chartView.timezone = event.target.value || 'UTC';
            persistChartSettings();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartScaleModeSelect')?.addEventListener('change', (event) => {
            state.chartView.scaleMode = event.target.value || 'auto';
            persistChartSettings();
            resetChartPriceScaleForMode();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#chartTradingPanelBtn')?.addEventListener('click', togglePaperTradePanel);
        $('#paperTradePlaceBtn')?.addEventListener('click', placePaperTrade);
        bindFloatingToolbarEvents();
        const deleteSelectedBtn = $('#chartDeleteSelectedBtn');
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', () => {
                if (deleteSelectedDrawings() && state.activeMarketData) {
                    drawMarketChart(state.activeMarketData);
                }
            });
        }
        $$('#chartQuickMenu [data-chart-menu-action]').forEach((button) => {
            button.addEventListener('click', () => handleChartQuickMenuAction(button.dataset.chartMenuAction));
        });
        document.addEventListener('mousedown', (event) => {
            if (!event.target.closest('#chartQuickMenu')) hideChartQuickMenu();
        });
    }

    function handleChartWheel(event) {
        if (!state.activeMarketData || !state.chartView.lastLayout) return;
        event.preventDefault();
        const layout = state.chartView.lastLayout;
        const point = canvasPoint(event, $('#marketChart'));
        const oldBars = state.chartView.barsOnScreen;
        const ratio = clamp((point.x - layout.priceArea.left) / Math.max(1, layout.priceArea.width), 0, 1);
        const indexAtMouse = layout.first + ratio * oldBars;
        const zoomFactor = event.deltaY > 0 ? 1.16 : 0.86;
        const maxBars = layout.allBars.length;
        const newBars = clamp(Math.round(oldBars * zoomFactor), Math.min(12, maxBars), maxBars);
        const newFirst = indexAtMouse - ratio * newBars;
        const offsetLimits = chartRightOffsetLimits(maxBars, newBars);
        state.chartView.barsOnScreen = newBars;
        state.chartView.rightOffset = clamp(Math.round(maxBars - newFirst - newBars), offsetLimits.min, offsetLimits.max);
        drawMarketChart(state.activeMarketData);
        if (state.activeTab === 'pa-training') persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
    }

    function findPaTrainingLevelAtPoint(point, layout) {
        const trade = paTrainingSelectedTeachingCase();
        if (!trade || !paTrainingCaseFitsChartData(trade, layout.data)) return '';
        const entryIndex = chartIndexForTime(layout, trade.entryTime || trade.anchorTime);
        if (!Number.isFinite(entryIndex)) return '';
        const startX = clamp(layout.x(entryIndex), layout.priceArea.left, layout.priceArea.right);
        if (point.x < startX || point.x > layout.priceArea.right || point.y < layout.priceArea.top || point.y > layout.priceArea.bottom) return '';
        const levels = [
            ['entryPrice', Number(trade.entryPrice)],
            ['initialStop', Number(trade.initialStop)],
            ['targetPrice', Number(trade.targetPrice)],
        ];
        const nearest = levels
            .map(([key, price]) => ({key, distance: Number.isFinite(price) ? Math.abs(layout.y(price) - point.y) : Infinity}))
            .sort((a, b) => a.distance - b.distance)[0];
        return nearest?.distance <= 12 ? nearest.key : '';
    }

    function updatePaTrainingLevelDrag(drag, point, layout) {
        const trade = paTrainingSelectedTeachingCase();
        if (!trade || !layout.priceAtY) return;
        const nextPrice = layout.priceAtY(point.y);
        const long = trade.direction !== 'short';
        const entry = drag.level === 'entryPrice' ? nextPrice : Number(trade.entryPrice);
        const sl = drag.level === 'initialStop' ? nextPrice : Number(trade.initialStop);
        const tp1 = drag.level === 'targetPrice' ? nextPrice : Number(trade.targetPrice);
        const risk = Math.abs(entry - sl);
        if (![entry, sl, tp1].every(Number.isFinite) || risk <= 0) return;
        if (long ? !(sl < entry && entry < tp1) : !(tp1 < entry && entry < sl)) return;
        trade[drag.level] = nextPrice;
        trade.riskR = Math.abs(entry - sl);
        trade.rr = Math.abs(tp1 - entry) / trade.riskR;
        trade.rrTarget2 = Math.abs(tp2 - entry) / trade.riskR;
        trade.corrected = true;
        trade.resultStatus = 'not_recalculated';
        trade.exitType = trade.exitPrice = trade.netR = null;
        drag.changed = true;
    }

    function handleChartMouseDown(event) {
        if (!state.activeMarketData || !state.chartView.lastLayout) return;
        hideChartQuickMenu();
        const layout = state.chartView.lastLayout;
        const point = canvasPoint(event, $('#marketChart'));
        const domainPoint = chartDomainPoint(point, layout);
        const drawingPoint = chartDomainPoint(point, layout, shouldSnapForEvent(event));
        const isAxis = point.x >= layout.axisArea.left;
        const tool = state.chartView.activeTool;

        if (event.button === 2) {
            event.preventDefault();
            if (tool !== 'cursor' && tool !== 'select' || state.chartView.draftDrawing) {
                cancelActiveDrawingTool();
                return;
            }
            state.chartView.selectionBox = {
                start: domainPoint,
                end: domainPoint,
            };
            state.chartView.drag = {
                mode: isAxis ? 'context-menu' : 'select',
                button: 2,
                x: point.x,
                y: point.y,
            };
            $('#marketChart').style.cursor = 'crosshair';
            if (!isAxis) drawMarketChart(state.activeMarketData);
            return;
        }

        if (event.button !== 0) return;

        if (!isAxis && (tool === 'cursor' || tool === 'select')) {
            const paLevel = findPaTrainingLevelAtPoint(point, layout);
            if (paLevel) {
                const trade = paTrainingSelectedTeachingCase();
                trade.originalTrade ||= paTrainingTradeSnapshot(trade);
                state.chartView.drag = { mode: 'edit-pa-level', level: paLevel, x: point.x, y: point.y, original: Number(trade[paLevel === 'entryPrice' ? 'entryPrice' : paLevel]) };
                $('#marketChart').style.cursor = 'ns-resize';
                return;
            }
        }

        if (!isAxis && (tool === 'cursor' || tool === 'select')) {
            const editableDrawing = findEditableDrawing(point, layout);
            if (editableDrawing) {
                startDrawingEdit(editableDrawing, point, domainPoint);
                return;
            }
        }

        if (!isAxis && tool !== 'erase') {
            const editablePosition = findEditablePosition(point, layout);
            if (editablePosition) {
                startPositionEdit(editablePosition, point, domainPoint);
                return;
            }
        }

        if (tool === 'select' && !isAxis) {
            startSelectionDrag(point, domainPoint, 0);
            return;
        }

        if (tool === 'rect' && !isAxis) {
            startBoxDrawingDrag(point, drawingPoint);
            return;
        }

        if (tool !== 'cursor' && !isAxis) {
            handleDrawingClick(tool, drawingPoint, point, layout);
            return;
        }

        state.chartView.drag = {
            mode: isAxis ? 'scale' : 'pan',
            x: point.x,
            y: point.y,
            rightOffset: state.chartView.rightOffset,
            panPixelOffset: state.chartView.panPixelOffset,
            priceScale: state.chartView.priceScale,
            priceOffset: state.chartView.priceOffset,
            priceRange: Math.max(0.0000001, Number.isFinite(layout.scaleHi - layout.scaleLo) ? layout.scaleHi - layout.scaleLo : layout.hi - layout.lo),
        };
        $('#marketChart').style.cursor = isAxis ? 'ns-resize' : 'grabbing';
    }

    function handleChartMouseMove(event) {
        if (!state.activeMarketData || !state.chartView.lastLayout) return;
        const canvas = $('#marketChart');
        const layout = state.chartView.lastLayout;
        const point = canvasPoint(event, canvas);
        state.chartView.crosshairPoint = point;
        const drag = state.chartView.drag;

        if (!drag) {
            const paLevel = point.x < layout.axisArea.left ? findPaTrainingLevelAtPoint(point, layout) : '';
            const editablePosition = state.chartView.activeTool !== 'erase' && point.x < layout.axisArea.left
                ? (findEditablePosition(point, layout) || findEditableDrawing(point, layout))
                : null;
            canvas.style.cursor = point.x >= layout.axisArea.left
                ? 'ns-resize'
                : paLevel
                  ? 'ns-resize'
                  : editablePosition
                  ? drawingEditCursor(editablePosition.handle)
                : state.chartView.activeTool === 'cursor'
                  ? 'grab'
                  : state.chartView.activeTool === 'select'
                    ? 'crosshair'
                  : 'crosshair';
            if (state.chartView.draftDrawing) {
                updateDraftDrawing(point, layout, event);
                return;
            }
            if (state.chartView.candleInfoVisible) renderChartTooltip(point, layout);
            else hideChartTooltip();
            scheduleChartCrosshairDraw();
            return;
        }

        const dx = point.x - drag.x;
        const dy = point.y - drag.y;
        if (drag.mode === 'pan') {
            const panPixels = (Number(drag.panPixelOffset) || 0) + dx;
            const deltaBars = Math.trunc(panPixels / Math.max(1, layout.barStep));
            const offsetLimits = chartRightOffsetLimits(layout.allBars.length, state.chartView.barsOnScreen);
            const requestedOffset = drag.rightOffset + deltaBars;
            const nextOffset = clamp(requestedOffset, offsetLimits.min, offsetLimits.max);
            state.chartView.rightOffset = nextOffset;
            state.chartView.panPixelOffset = nextOffset === requestedOffset ? panPixels - deltaBars * layout.barStep : 0;
            state.chartView.priceOffset = drag.priceOffset + (dy / Math.max(1, layout.priceArea.height)) * drag.priceRange;
        } else if (drag.mode === 'scale') {
            state.chartView.priceScale = clamp(drag.priceScale * (1 - dy * 0.012), 0.2, 20);
        } else if (drag.mode === 'select') {
            state.chartView.selectionBox.end = chartDomainPoint(point, layout);
        } else if (drag.mode === 'draw-box') {
            updateDraftDrawing(point, layout, event);
        } else if (drag.mode === 'edit-position') {
            updatePositionDrawingDrag(drag, point, layout);
        } else if (drag.mode === 'edit-drawing') {
            updateGenericDrawingDrag(drag, point, layout);
        } else if (drag.mode === 'edit-pa-level') {
            updatePaTrainingLevelDrag(drag, point, layout);
            drawPaTrainingLevelOverlay(layout);
            scheduleChartCrosshairDraw();
            return;
        }
        scheduleChartInteractionDraw(state.activeMarketData);
    }

    function handleChartMouseUp(event) {
        const drag = state.chartView.drag;
        if (drag?.mode === 'edit-pa-level' && state.activeMarketData) {
            const trade = paTrainingSelectedTeachingCase();
            const changes = trade ? {
                zoneLow: Number(trade.zoneLow ?? trade.zone?.low), zoneHigh: Number(trade.zoneHigh ?? trade.zone?.high),
                entryPrice: Number(trade.entryPrice), initialStop: Number(trade.initialStop),
                targetPrice: Number(trade.targetPrice), targetPrice2: Number(trade.targetPrice2 || (trade.direction === 'short' ? Number(trade.entryPrice) - Math.abs(Number(trade.entryPrice) - Number(trade.initialStop)) * 2 : Number(trade.entryPrice) + Math.abs(Number(trade.entryPrice) - Number(trade.initialStop)) * 2)),
            } : null;
            state.chartView.drag = null;
            const canvas = $('#marketChart');
            if (canvas) canvas.style.cursor = state.chartView.activeTool === 'cursor' ? 'grab' : 'crosshair';
            if (drag.changed && changes && Number.isFinite(changes.zoneLow) && Number.isFinite(changes.zoneHigh)) applyPaCaseCorrection(changes, `Kéo chỉnh ${drag.level} trên biểu đồ`);
            return;
        }
        if ((drag?.mode === 'select' || drag?.mode === 'zoom-box') && state.activeMarketData && state.chartView.lastLayout) {
            const layout = state.chartView.lastLayout;
            const canvas = $('#marketChart');
            const point = event ? canvasPoint(event, canvas) : { x: drag.x, y: drag.y };
            const moved = Math.hypot(point.x - drag.x, point.y - drag.y);
            if (drag.mode === 'zoom-box') {
                applyZoomBox(state.chartView.selectionBox, layout);
                state.chartView.selectionBox = null;
                returnToCursorAfterDrawing();
                if (state.activeTab === 'pa-training') persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
            } else if (drag.button === 2 && moved < 8) {
                const id = selectNearestDrawing(point, layout, 22);
                state.chartView.selectedDrawingIds = id ? [id] : [];
                state.chartView.selectionBox = null;
                openChartQuickMenu(point);
            } else {
                if (drag.mode === 'select' && moved >= 8 && state.chartView.selectionBox?.start && state.chartView.selectionBox?.end) {
                    const firstPoint = state.chartView.selectionBox.start;
                    const lastPoint = state.chartView.selectionBox.end;
                    const firstTime = Number(firstPoint.time ?? timeToSeconds(firstPoint.time_iso));
                    const lastTime = Number(lastPoint.time ?? timeToSeconds(lastPoint.time_iso));
                    if (Number.isFinite(firstTime) && Number.isFinite(lastTime)) {
                        state.chartView.timeframeSelection = {
                            startTime: Math.min(firstTime, lastTime),
                            endTime: Math.max(firstTime, lastTime),
                            startPrice: Math.min(Number(firstPoint.price), Number(lastPoint.price)),
                            endPrice: Math.max(Number(firstPoint.price), Number(lastPoint.price)),
                            symbol: state.activeMarketData.symbol,
                        };
                        updateChartSelectionJumpButtons();
                    }
                }
                state.chartView.selectedDrawingIds = selectDrawingsInBox(state.chartView.selectionBox, layout);
                state.chartView.selectionBox = null;
            }
            drawMarketChart(state.activeMarketData);
        } else if (drag?.mode === 'context-menu' && state.activeMarketData && state.chartView.lastLayout) {
            const point = event ? canvasPoint(event, $('#marketChart')) : { x: drag.x, y: drag.y };
            state.chartView.selectionBox = null;
            openChartQuickMenu(point);
            drawMarketChart(state.activeMarketData);
        } else if (drag?.mode === 'draw-box' && state.activeMarketData && state.chartView.lastLayout) {
            const layout = state.chartView.lastLayout;
            const point = event ? canvasPoint(event, $('#marketChart')) : { x: drag.x, y: drag.y };
            updateDraftDrawing(point, layout, event);
            const moved = Math.hypot(point.x - drag.x, point.y - drag.y);
            const drawing = state.chartView.draftDrawing;
            state.chartView.drag = null;
            state.chartView.draftDrawing = null;
            if (drawing && moved >= 6) {
                commitDrawing(drawing);
                returnToCursorAfterDrawing();
            } else if (state.activeMarketData) {
                drawMarketChart(state.activeMarketData);
            }
            return;
        } else if (drag?.mode === 'edit-position' && state.activeMarketData) {
            state.chartView.selectionBox = null;
            persistChartDrawings();
            drawMarketChart(state.activeMarketData);
        } else if (drag?.mode === 'edit-drawing' && state.activeMarketData) {
            state.chartView.selectionBox = null;
            persistChartDrawings();
            drawMarketChart(state.activeMarketData);
        }
        state.chartView.drag = null;
        const canvas = $('#marketChart');
        if (canvas) canvas.style.cursor = state.chartView.activeTool === 'cursor' ? 'grab' : 'crosshair';
        if (drag?.mode === 'pan' || drag?.mode === 'scale') drawMarketChart(state.activeMarketData);
        if ((drag?.mode === 'pan' || drag?.mode === 'scale') && state.activeTab === 'pa-training' && state.activeMarketData) {
            persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
        }
    }

    function startSelectionDrag(point, domainPoint, button = 0) {
        state.chartView.selectedDrawingIds = [];
        state.chartView.selectionBox = {
            start: domainPoint,
            end: domainPoint,
        };
        state.chartView.drag = {
            mode: 'select',
            button,
            x: point.x,
            y: point.y,
        };
        $('#marketChart').style.cursor = 'crosshair';
        drawMarketChart(state.activeMarketData);
    }

    function startBoxDrawingDrag(point, domainPoint) {
        state.chartView.selectedDrawingIds = [];
        state.chartView.draftDrawing = styledDrawing({
            type: 'rect',
            points: [domainPoint, domainPoint],
        });
        state.chartView.drag = {
            mode: 'draw-box',
            x: point.x,
            y: point.y,
        };
        $('#marketChart').style.cursor = 'crosshair';
        drawMarketChart(state.activeMarketData);
    }
    function startPositionEdit(editablePosition, point, domainPoint) {
        pushDrawingHistory();
        state.chartView.selectedDrawingIds = [editablePosition.id];
        state.chartView.selectionBox = null;
        state.chartView.drag = {
            mode: 'edit-position',
            id: editablePosition.id,
            handle: editablePosition.handle,
            x: point.x,
            y: point.y,
            startDomain: domainPoint,
            drawing: cloneDrawing(editablePosition.drawing),
        };
        $('#marketChart').style.cursor = editablePosition.handle === 'move' ? 'move' : 'ns-resize';
        drawMarketChart(state.activeMarketData);
    }

    function handleChartDoubleClick(event) {
        if (!state.activeMarketData || !state.chartView.lastLayout) return;
        if (finishMultiPointDrawing()) {
            event.preventDefault();
        }
    }

    function handleChartMouseLeave() {
        if (state.chartView.drag) return;
        state.chartView.crosshairPoint = null;
        hideChartTooltip();
        scheduleChartCrosshairDraw();
    }

    function handleChartContextMenu(event) {
        event.preventDefault();
    }

    function handleChartKeyDown(event) {
        if (isFormFieldActive()) return;
        const key = String(event.key || '').toLowerCase();
        if ((event.ctrlKey || event.metaKey) && key === 'z') {
            event.preventDefault();
            if (event.shiftKey) {
                redoChartDrawing();
            } else {
                undoChartDrawing();
            }
            return;
        }
        if ((event.ctrlKey || event.metaKey) && key === 'y') {
            event.preventDefault();
            redoChartDrawing();
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            cancelActiveDrawingTool();
            return;
        }
        if (event.key === 'Enter' && state.chartView.draftDrawing) {
            event.preventDefault();
            finishMultiPointDrawing();
            return;
        }
        const shortcutTools = {
            l: 'long-position',
            s: 'short-position',
            t: 'trendline',
            r: 'ray',
            h: 'hline',
            v: 'vline',
            f: 'fib',
            m: 'measure',
            n: 'text',
            a: 'alert',
            e: 'erase',
        };
        if (!event.ctrlKey && !event.metaKey && shortcutTools[key]) {
            event.preventDefault();
            setChartTool(shortcutTools[key]);
            return;
        }
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            previousTrade();
            return;
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            nextTradeSelection();
            return;
        }
        if (event.code === 'Space') {
            event.preventDefault();
            toggleChartReplay();
            return;
        }
        if (event.key !== 'Delete' && event.key !== 'Backspace') return;
        if (!state.activeMarketData || !(state.chartView.selectedDrawingIds || []).length) return;
        event.preventDefault();
        if (deleteSelectedDrawings()) drawMarketChart(state.activeMarketData);
    }

    function handleChartQuickMenuAction(action) {
        const quickMenu = state.chartView.quickMenu;
        const layout = state.chartView.lastLayout;
        if (action === 'fit-case') {
            focusPaTrainingTeachingCaseWindow(paTrainingSelectedTeachingCase(), true);
        } else if (action === 'fit-full') {
            fitFullChart();
        } else if (action === 'fit-horizontal') {
            fitChartHorizontally();
        } else if (action === 'fit-vertical') {
            fitChartVertically();
        } else if (action === 'toggle-candle-info') {
            state.chartView.candleInfoVisible = !state.chartView.candleInfoVisible;
            if (state.chartView.candleInfoVisible && quickMenu && layout) {
                renderChartTooltip(quickMenu.point, layout);
            } else {
                hideChartTooltip();
            }
        } else if (action === 'cursor') {
            setChartTool('cursor');
        } else if (action === 'select') {
            setChartTool('select');
        } else if (action === 'copy-selected') {
            copySelectedDrawing();
        } else if (action === 'paste-drawing') {
            pasteCopiedDrawing();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'duplicate-selected') {
            duplicateSelectedDrawings();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'edit-text') {
            editSelectedDrawingText();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'toggle-lock') {
            toggleSelectedDrawingLock();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'toggle-hidden') {
            toggleSelectedDrawingHidden();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'bring-front') {
            reorderSelectedDrawings('front');
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'send-back') {
            reorderSelectedDrawings('back');
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'add-alert') {
            addAlertOnSelectedDrawing();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'add-favorite') {
            addSelectedToolToFavorites();
        } else if (action === 'delete-selected') {
            deleteSelectedDrawings();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'erase-nearest' && quickMenu && layout) {
            eraseNearestDrawing(quickMenu.point, layout);
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'remove-selected') {
            deleteSelectedDrawings();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        } else if (action === 'remove-all') {
            resetChartView(true);
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        }
        hideChartQuickMenu();
    }

    function openChartQuickMenu(point) {
        const menu = $('#chartQuickMenu');
        if (!menu) return;
        const wrap = menu.parentElement;
        state.chartView.quickMenu = { point: { x: point.x, y: point.y } };
        menu.hidden = false;
        menu.style.left = '0px';
        menu.style.top = '0px';
        const left = clamp(point.x + 8, 4, Math.max(4, wrap.clientWidth - menu.offsetWidth - 4));
        const top = clamp(point.y + 8, 4, Math.max(4, wrap.clientHeight - menu.offsetHeight - 4));
        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
        const hasSelection = !!(state.chartView.selectedDrawingIds || []).length;
        const fitCaseButton = menu.querySelector('[data-chart-menu-action="fit-case"]');
        if (fitCaseButton) fitCaseButton.disabled = state.activeTab !== 'pa-training' || !paTrainingSelectedTeachingCase();
        const candleInfoButton = menu.querySelector('[data-chart-menu-action="toggle-candle-info"]');
        if (candleInfoButton) {
            candleInfoButton.textContent = state.chartView.candleInfoVisible ? 'Ẩn thông tin nến' : 'Hiện thông tin nến';
            candleInfoButton.setAttribute('aria-pressed', String(state.chartView.candleInfoVisible));
        }
        ['duplicate-selected', 'edit-text', 'toggle-lock', 'toggle-hidden', 'delete-selected'].forEach((action) => {
            const button = menu.querySelector(`[data-chart-menu-action="${action}"]`);
            if (button) button.disabled = !hasSelection;
        });
        ['copy-selected', 'bring-front', 'send-back', 'add-alert', 'remove-selected'].forEach((action) => {
            const button = menu.querySelector(`[data-chart-menu-action="${action}"]`);
            if (button) button.disabled = !hasSelection;
        });
        const pasteButton = menu.querySelector('[data-chart-menu-action="paste-drawing"]');
        if (pasteButton) pasteButton.disabled = !state.chartView.copiedDrawing;
        const deleteButton = menu.querySelector('[data-chart-menu-action="delete-selected"]');
        if (deleteButton) {
            deleteButton.disabled = !(state.chartView.selectedDrawingIds || []).length;
        }
    }

    function hideChartQuickMenu() {
        const menu = $('#chartQuickMenu');
        if (menu) menu.hidden = true;
        state.chartView.quickMenu = null;
    }

    function renderChartTooltip(point, layout) {
        const tooltip = $('#chartTooltip');
        if (!tooltip || !layout?.data) return;
        if (point.x < layout.priceArea.left || point.x > layout.priceArea.right || point.y < layout.priceArea.top || point.y > layout.panelBottom) {
            hideChartTooltip();
            return;
        }
        const index = Math.round(layout.barIndexAtX(point.x));
        if (index < 0 || index >= layout.allBars.length) {
            hideChartTooltip();
            return;
        }
        const bar = layout.allBars[index];
        if (!bar) {
            hideChartTooltip();
            return;
        }
        const candleNumber = index + 1;
        const candleTotal = layout.allBars.length;
        tooltip.innerHTML = `
            <strong>${escapeHtml(formatChartTime(bar.time_iso || bar.time))}</strong>
            <span>Nến: #${escapeHtml(number(candleNumber))} / ${escapeHtml(number(candleTotal))}</span>
            <span>Mở: ${escapeHtml(formatPrice(bar.open, layout.data.symbol))} | Cao: ${escapeHtml(formatPrice(bar.high, layout.data.symbol))}</span>
            <span>Thấp: ${escapeHtml(formatPrice(bar.low, layout.data.symbol))} | Đóng: ${escapeHtml(formatPrice(bar.close, layout.data.symbol))}</span>
            <span>Khối lượng: ${escapeHtml(number(bar.tick_volume || bar.real_volume || 0))} | Spread: ${escapeHtml(formatNumberValue(bar.spread || 0))}</span>
        `;
        const wrap = tooltip.parentElement;
        tooltip.classList.add('is-visible');
        tooltip.style.left = '0px';
        tooltip.style.top = '0px';
        const left = clamp(point.x + 14, 6, Math.max(6, wrap.clientWidth - tooltip.offsetWidth - 6));
        const top = clamp(point.y + 14, 6, Math.max(6, wrap.clientHeight - tooltip.offsetHeight - 6));
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    }

    function hideChartTooltip() {
        const tooltip = $('#chartTooltip');
        if (tooltip) tooltip.classList.remove('is-visible');
    }

    function isFormFieldActive() {
        const element = document.activeElement;
        if (!element) return false;
        return ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName);
    }

    function handleDrawingClick(tool, domainPoint, screenPoint, layout) {
        if (state.chartView.drawingsLocked && tool !== 'select' && tool !== 'cursor') {
            showToast('Hình vẽ đang bị khóa');
            return;
        }
        if (tool === 'erase') {
            eraseNearestDrawing(screenPoint, layout);
            drawMarketChart(state.activeMarketData);
            return;
        }
        if (tool === 'zoom-box') {
            startSelectionDrag(screenPoint, domainPoint, 0);
            state.chartView.drag.mode = 'zoom-box';
            return;
        }
        if (tool === 'long-position' || tool === 'short-position') {
            commitDrawing(createPositionDrawing(tool === 'long-position' ? 'long' : 'short', domainPoint, layout));
            returnToCursorAfterDrawing();
            return;
        }
        if (TEXT_TOOLS.has(tool)) {
            const text = window.prompt(`${DRAWING_TOOL_LABELS[tool] || 'Ghi chú'} trên biểu đồ`, DRAWING_TOOL_LABELS[tool] || 'Ghi chú');
            if (text === null) return;
            commitDrawing(createTextDrawing(tool, domainPoint, text));
            returnToCursorAfterDrawing();
            return;
        }
        if (ONE_CLICK_TOOLS.has(tool)) {
            commitDrawing(createOneClickDrawing(tool, domainPoint, layout));
            returnToCursorAfterDrawing();
            return;
        }
        if (MULTI_POINT_TOOLS.has(tool)) {
            handleMultiPointDrawingClick(tool, domainPoint);
            return;
        }
        handleTwoPointDrawingClick(tool, domainPoint);
    }

    function handleTwoPointDrawingClick(tool, domainPoint) {
        if (!state.chartView.draftDrawing) {
            state.chartView.draftDrawing = styledDrawing({
                type: tool,
                points: [domainPoint, domainPoint],
            });
            drawMarketChart(state.activeMarketData);
            return;
        }
        state.chartView.draftDrawing.points[1] = domainPoint;
        commitDrawing(state.chartView.draftDrawing);
        state.chartView.draftDrawing = null;
        returnToCursorAfterDrawing();
    }

    function handleMultiPointDrawingClick(tool, domainPoint) {
        const draft = state.chartView.draftDrawing;
        if (!draft || draft.type !== tool) {
            state.chartView.draftDrawing = styledDrawing({
                type: tool,
                points: [domainPoint],
                previewPoint: domainPoint,
            });
        } else {
            draft.points.push(domainPoint);
            draft.previewPoint = domainPoint;
        }
        drawMarketChart(state.activeMarketData);
    }

    function finishMultiPointDrawing() {
        const draft = state.chartView.draftDrawing;
        if (!draft || !MULTI_POINT_TOOLS.has(draft.type)) return false;
        if ((draft.points || []).length < 2) {
            state.chartView.draftDrawing = null;
            drawMarketChart(state.activeMarketData);
            return true;
        }
        delete draft.previewPoint;
        commitDrawing(draft);
        state.chartView.draftDrawing = null;
        returnToCursorAfterDrawing();
        return true;
    }

    function commitDrawing(drawing) {
        if (!drawing) return;
        getDrawingId(drawing);
        pushDrawingHistory();
        getChartDrawings(state.activeMarketData).push(drawing);
        state.chartView.selectedDrawingIds = [getDrawingId(drawing)];
        persistChartDrawings();
        drawMarketChart(state.activeMarketData);
    }

    function styledDrawing(drawing) {
        const style = state.chartView.drawingStyle || {};
        const zoneType = drawing.type === 'rect' ? state.chartView.pendingZoneType : null;
        const zoneColor = zoneType === 'supply' ? '#ef4444' : zoneType === 'demand' ? '#10b981' : null;
        return {
            color: zoneColor || style.color || defaultDrawingColor(drawing.type),
            width: Number(style.width || 2),
            lineStyle: style.lineStyle || 'solid',
            opacity: zoneType ? 14 : Number(style.opacity ?? 16),
            textSize: Number(style.textSize || 12),
            ...(zoneType ? { zoneType, label: zoneType === 'supply' ? 'VÙNG CANH SELL' : 'VÙNG CANH BUY' } : {}),
            ...drawing,
        };
    }

    function createOneClickDrawing(tool, domainPoint, layout) {
        if (tool === 'alert') {
            const label = window.prompt('Nhãn cảnh báo giá', `Cảnh báo ${formatPrice(domainPoint.price, state.activeMarketData?.symbol)}`);
            if (label === null) return null;
            return styledDrawing({
                type: 'alert',
                price: domainPoint.price,
                label: label.trim() || 'Cảnh báo giá',
                color: '#bc3d3d',
            });
        }
        if (tool === 'hline' || tool === 'hray') {
            return styledDrawing({ type: tool, price: domainPoint.price, startBar: domainPoint.barIndex, startTime: domainPoint.time, startTimeIso: domainPoint.time_iso });
        }
        if (tool === 'vline') {
            return styledDrawing({ type: 'vline', barIndex: domainPoint.barIndex, time: domainPoint.time, time_iso: domainPoint.time_iso, sourceTimeframe: domainPoint.sourceTimeframe });
        }
        if (tool === 'cross-line') {
            return styledDrawing({ type: 'cross-line', price: domainPoint.price, barIndex: domainPoint.barIndex, time: domainPoint.time, time_iso: domainPoint.time_iso, sourceTimeframe: domainPoint.sourceTimeframe });
        }
        if (tool === 'price-label') {
            return styledDrawing({ type: 'price-label', price: domainPoint.price, point: domainPoint, label: formatPrice(domainPoint.price, state.activeMarketData?.symbol) });
        }
        return styledDrawing({
            type: tool,
            point: domainPoint,
            text: DRAWING_TOOL_LABELS[tool] || tool,
        });
    }

    function createTextDrawing(tool, domainPoint, text) {
        return styledDrawing({
            type: tool,
            point: domainPoint,
            text: text.trim() || DRAWING_TOOL_LABELS[tool] || 'Ghi chú',
            color: state.chartView.drawingStyle?.color || '#1f2937',
        });
    }

    function updateDraftDrawing(point, layout, event = null) {
        const draft = state.chartView.draftDrawing;
        if (!draft) return;
        const domainPoint = chartDomainPoint(point, layout, shouldSnapForEvent(event));
        if (MULTI_POINT_TOOLS.has(draft.type)) {
            draft.previewPoint = domainPoint;
        } else {
            draft.points[1] = domainPoint;
        }
        scheduleChartInteractionDraw(state.activeMarketData);
    }

    function eraseNearestDrawing(point, layout) {
        if (state.chartView.drawingsLocked) return;
        const drawings = getChartDrawings(state.activeMarketData);
        if (!drawings.length) return;
        let bestIndex = -1;
        let bestDistance = Infinity;
        drawings.forEach((drawing, index) => {
            if (drawing.locked) return;
            const distance = drawingDistance(point, drawing, layout);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestIndex = index;
            }
        });
        if (bestIndex >= 0 && bestDistance < 28) {
            pushDrawingHistory();
            const removedId = getDrawingId(drawings[bestIndex]);
            drawings.splice(bestIndex, 1);
            state.chartView.selectedDrawingIds = (state.chartView.selectedDrawingIds || []).filter((id) => id !== removedId);
            persistChartDrawings();
        }
    }

    function deleteSelectedDrawings() {
        if (state.chartView.drawingsLocked) return false;
        if (!state.activeMarketData) return false;
        const selectedIds = new Set(state.chartView.selectedDrawingIds || []);
        if (!selectedIds.size) return false;
        const drawings = getChartDrawings(state.activeMarketData);
        const before = drawings.length;
        pushDrawingHistory();
        for (let i = drawings.length - 1; i >= 0; i--) {
            if (selectedIds.has(getDrawingId(drawings[i])) && !drawings[i].locked) {
                drawings.splice(i, 1);
            }
        }
        state.chartView.selectedDrawingIds = [];
        state.chartView.selectionBox = null;
        if (drawings.length !== before) persistChartDrawings();
        return drawings.length !== before;
    }

    function selectedDrawingObjects(ids = state.chartView.selectedDrawingIds || []) {
        const selectedIds = new Set(ids);
        return getChartDrawings(state.activeMarketData).filter((drawing) => selectedIds.has(getDrawingId(drawing)));
    }

    function toggleDrawingHidden(id) {
        const drawing = findDrawingById(id);
        if (!drawing) return;
        pushDrawingHistory();
        drawing.hidden = !drawing.hidden;
        state.chartView.selectedDrawingIds = [id];
        persistChartDrawings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function toggleDrawingLocked(id) {
        const drawing = findDrawingById(id);
        if (!drawing) return;
        pushDrawingHistory();
        drawing.locked = !drawing.locked;
        state.chartView.selectedDrawingIds = [id];
        persistChartDrawings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function renameDrawing(id) {
        const drawing = findDrawingById(id);
        if (!drawing) return;
        const name = window.prompt('Tên hình vẽ', drawing.name || drawingDisplayName(drawing));
        if (name === null) return;
        pushDrawingHistory();
        drawing.name = name.trim() || '';
        state.chartView.selectedDrawingIds = [id];
        persistChartDrawings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function groupSelectedDrawings(fallbackId = null) {
        const ids = state.chartView.selectedDrawingIds?.length ? state.chartView.selectedDrawingIds : (fallbackId ? [fallbackId] : []);
        const drawings = selectedDrawingObjects(ids);
        if (!drawings.length) return;
        const group = window.prompt('Tên nhóm', drawings[0].group || 'Nhóm 1');
        if (group === null) return;
        pushDrawingHistory();
        drawings.forEach((drawing) => {
            drawing.group = group.trim() || '';
        });
        state.chartView.selectedDrawingIds = ids;
        persistChartDrawings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function toggleSelectedDrawingHidden() {
        const drawings = selectedDrawingObjects();
        if (!drawings.length) return;
        const nextHidden = !drawings.every((drawing) => drawing.hidden);
        pushDrawingHistory();
        drawings.forEach((drawing) => {
            drawing.hidden = nextHidden;
        });
        persistChartDrawings();
    }

    function toggleSelectedDrawingLock() {
        const drawings = selectedDrawingObjects();
        if (!drawings.length) return;
        const nextLocked = !drawings.every((drawing) => drawing.locked);
        pushDrawingHistory();
        drawings.forEach((drawing) => {
            drawing.locked = nextLocked;
        });
        persistChartDrawings();
    }

    function duplicateSelectedDrawings(ids = state.chartView.selectedDrawingIds || []) {
        if (state.chartView.drawingsLocked || !state.activeMarketData) return;
        const drawings = selectedDrawingObjects(ids).filter((drawing) => !drawing.locked);
        if (!drawings.length) return;
        pushDrawingHistory();
        const clones = drawings.map((drawing) => {
            const clone = cloneDrawing(drawing);
            delete clone.id;
            nudgeDrawing(clone);
            getDrawingId(clone);
            clone.hidden = false;
            return clone;
        });
        getChartDrawings(state.activeMarketData).push(...clones);
        state.chartView.selectedDrawingIds = clones.map((drawing) => getDrawingId(drawing));
        persistChartDrawings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function copySelectedDrawing() {
        const drawing = selectedDrawingObjects()[0];
        if (!drawing) return;
        state.chartView.copiedDrawing = cloneDrawing(drawing);
        showToast('Đã sao chép hình vẽ');
    }

    function pasteCopiedDrawing() {
        if (!state.chartView.copiedDrawing || !state.activeMarketData) return;
        pushDrawingHistory();
        const clone = cloneDrawing(state.chartView.copiedDrawing);
        delete clone.id;
        nudgeDrawing(clone);
        getDrawingId(clone);
        getChartDrawings(state.activeMarketData).push(clone);
        state.chartView.selectedDrawingIds = [getDrawingId(clone)];
        persistChartDrawings();
    }

    function reorderSelectedDrawings(where) {
        if (!state.activeMarketData) return;
        const selectedIds = new Set(state.chartView.selectedDrawingIds || []);
        if (!selectedIds.size) return;
        pushDrawingHistory();
        const drawings = getChartDrawings(state.activeMarketData);
        const selected = drawings.filter((drawing) => selectedIds.has(getDrawingId(drawing)));
        const rest = drawings.filter((drawing) => !selectedIds.has(getDrawingId(drawing)));
        state.chartView.drawings[chartDrawingKey(state.activeMarketData)] = where === 'front' ? [...rest, ...selected] : [...selected, ...rest];
        persistChartDrawings();
    }

    function addAlertOnSelectedDrawing() {
        const drawing = selectedDrawingObjects()[0];
        if (!drawing || !state.activeMarketData) return;
        const price = drawing.price ?? drawing.entry ?? drawing.points?.[0]?.price ?? drawing.point?.price;
        if (!Number.isFinite(Number(price))) return;
        commitDrawing(styledDrawing({
            type: 'alert',
            price: Number(price),
            label: `Cảnh báo ${drawingDisplayName(drawing)}`,
            color: '#bc3d3d',
        }));
    }

    function addSelectedToolToFavorites() {
        const tool = selectedDrawingObjects()[0]?.type || state.chartView.activeTool;
        if (!tool || tool === 'cursor' || tool === 'select') return;
        if (!state.chartView.favoriteTools.includes(tool)) {
            state.chartView.favoriteTools.push(tool);
            persistChartFavorites();
            renderFavoriteToolbar();
            showToast('Đã thêm vào công cụ yêu thích');
        }
    }

    function nudgeDrawing(drawing) {
        const layout = state.chartView.lastLayout;
        const barDelta = 4;
        const priceDelta = layout ? Math.max(0.0000001, (layout.hi - layout.lo) * 0.025) : 0.0005;
        const shiftPoint = (point) => {
            if (!point) return point;
            const shifted = enrichDomainPointTime({ ...point, barIndex: Number(point.barIndex || 0) + barDelta, price: Number(point.price || 0) + priceDelta }, layout);
            Object.assign(point, shifted);
            return point;
        };
        if (drawing.type === 'hline' || drawing.type === 'alert') {
            drawing.price = Number(drawing.price || 0) + priceDelta;
        } else if (drawing.type === 'vline') {
            drawing.barIndex = Number(drawing.barIndex || 0) + barDelta;
        } else if (drawing.type === 'text') {
            drawing.point = shiftPoint(drawing.point || drawing.points?.[0] || { barIndex: 0, price: 0 });
        } else if (drawing.type === 'position') {
            drawing.entry = Number(drawing.entry || 0) + priceDelta;
            drawing.stop = Number(drawing.stop || 0) + priceDelta;
            drawing.target = Number(drawing.target || 0) + priceDelta;
            drawing.startBar = Number(drawing.startBar || 0) + barDelta;
            drawing.endBar = Number(drawing.endBar || 0) + barDelta;
            const startPoint = enrichDomainPointTime({ barIndex: drawing.startBar, price: drawing.entry }, layout);
            const endPoint = enrichDomainPointTime({ barIndex: drawing.endBar, price: drawing.entry }, layout);
            drawing.startTime = startPoint?.time;
            drawing.startTimeIso = startPoint?.time_iso;
            drawing.endTime = endPoint?.time;
            drawing.endTimeIso = endPoint?.time_iso;
        } else if (Array.isArray(drawing.points)) {
            drawing.points.forEach(shiftPoint);
        }
    }
    function editSelectedDrawingText() {
        const drawing = selectedDrawingObjects()[0];
        if (!drawing) return;
        const current = drawing.text || drawing.label || drawingDisplayName(drawing);
        const next = window.prompt('Nội dung ghi chú', current);
        if (next === null) return;
        pushDrawingHistory();
        if (drawing.type === 'text') {
            drawing.text = next.trim() || 'Ghi chú';
        } else {
            drawing.label = next.trim();
        }
        persistChartDrawings();
    }

    function exportChartDrawings() {
        if (!state.activeMarketData) return;
        const snapshot = {
            format: 'ai_trade_chart_drawings_v1',
            exported_at: new Date().toISOString(),
            symbol: state.activeMarketData.symbol,
            timeframe: state.activeMarketData.timeframe,
            chart_key: chartDataKey(state.activeMarketData),
            drawings: getChartDrawings(state.activeMarketData),
        };
        const blob = new Blob([JSON.stringify(snapshot, null, 2)], { type: 'application/json;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${snapshot.symbol || 'chart'}_${snapshot.timeframe || 'TF'}_drawings.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    async function importChartDrawings(event) {
        const file = event.target.files?.[0];
        if (!file || !state.activeMarketData) return;
        try {
            const parsed = JSON.parse(await file.text());
            const drawings = Array.isArray(parsed) ? parsed : parsed.drawings;
            if (!Array.isArray(drawings)) throw new Error('Tệp hình vẽ không hợp lệ');
            pushDrawingHistory();
            state.chartView.drawings[chartDrawingKey(state.activeMarketData)] = drawings.map((drawing) => {
                const copy = cloneDrawing(drawing);
                getDrawingId(copy);
                return copy;
            });
            state.chartView.selectedDrawingIds = [];
            persistChartDrawings();
            drawMarketChart(state.activeMarketData);
            showToast('Đã nhập hình vẽ');
        } catch (error) {
            showToast(error.message || 'Không nhập được hình vẽ');
        } finally {
            event.target.value = '';
        }
    }

    function findTradeAtPoint(point, layout) {
        const analytics = layout.analytics || getChartAnalytics(layout.data);
        const run = activeBacktestRun(analytics);
        const trades = filteredTrades(run?.trades || []);
        const cutoff = replayCutoffIndex(layout.data);
        let best = null;
        let bestDistance = Infinity;
        trades.forEach((trade) => {
            if (trade.entryIndex > cutoff) return;
            const endIndex = Math.min(trade.exitIndex, cutoff);
            const candidates = [
                { x: layout.x(trade.entryIndex), y: layout.y(trade.entryPrice) },
                { x: layout.x(endIndex), y: layout.y(trade.exitPrice) },
            ];
            candidates.forEach((candidate) => {
                const distance = Math.hypot(point.x - candidate.x, point.y - candidate.y);
                if (distance < bestDistance && distance <= 16) {
                    bestDistance = distance;
                    best = trade;
                }
            });
        });
        return best;
    }

    function drawingDistance(point, drawing, layout) {
        if (!drawing || drawing.hidden) return Infinity;
        if (drawing.type === 'hline' || drawing.type === 'alert') {
            return Math.abs(point.y - layout.y(drawing.price));
        }
        if (drawing.type === 'vline') {
            return Math.abs(point.x - layout.x(domainPointBarIndex(drawing, layout)));
        }
        if (drawing.type === 'cross-line') {
            return Math.min(Math.abs(point.x - layout.x(domainPointBarIndex(drawing, layout))), Math.abs(point.y - layout.y(drawing.price)));
        }
        if (TEXT_TOOLS.has(drawing.type) || drawing.type === 'price-label') {
            const bounds = drawingScreenBounds(drawing, layout);
            if (!bounds) return Infinity;
            const dx = point.x < bounds.left ? bounds.left - point.x : point.x > bounds.right ? point.x - bounds.right : 0;
            const dy = point.y < bounds.top ? bounds.top - point.y : point.y > bounds.bottom ? point.y - bounds.bottom : 0;
            return Math.sqrt(dx * dx + dy * dy);
        }
        if (['dot', 'pin', 'flag', 'icon', 'arrow-marker', 'up-arrow', 'down-arrow'].includes(drawing.type)) {
            const bounds = drawingScreenBounds(drawing, layout);
            if (!bounds) return Infinity;
            return point.x >= bounds.left && point.x <= bounds.right && point.y >= bounds.top && point.y <= bounds.bottom ? 0 : Math.min(
                Math.hypot(point.x - bounds.left, point.y - bounds.top),
                Math.hypot(point.x - bounds.right, point.y - bounds.bottom)
            );
        }
        if (drawing.points?.length >= 2) {
            const a = screenPointFromDomain(drawing.points[0], layout);
            const b = screenPointFromDomain(drawing.points[1], layout);
            if (drawing.type === 'rect') {
                const left = Math.min(a.x, b.x);
                const right = Math.max(a.x, b.x);
                const top = Math.min(a.y, b.y);
                const bottom = Math.max(a.y, b.y);
                const dx = point.x < left ? left - point.x : point.x > right ? point.x - right : 0;
                const dy = point.y < top ? top - point.y : point.y > bottom ? point.y - bottom : 0;
                return Math.sqrt(dx * dx + dy * dy);
            }
            if (drawing.type === 'ray') {
                return distanceToSegment(point, a, rayEndPoint(a, b, layout));
            }
            return distanceToSegment(point, a, b);
        }
        if (drawing.type === 'position') {
            const bounds = positionScreenBounds(drawing, layout);
            if (!bounds) return Infinity;
            const dx = point.x < bounds.left ? bounds.left - point.x : point.x > bounds.right ? point.x - bounds.right : 0;
            const dy = point.y < bounds.top ? bounds.top - point.y : point.y > bounds.bottom ? point.y - bounds.bottom : 0;
            return Math.sqrt(dx * dx + dy * dy);
        }
        return Infinity;
    }

    function canvasPoint(event, canvas) {
        const rect = canvas.getBoundingClientRect();
        const layout = state.chartView.lastLayout;
        const width = Number(layout?.width) || rect.width;
        const height = Number(layout?.height) || rect.height;
        return {
            x: (event.clientX - rect.left) * (rect.width ? width / rect.width : 1),
            y: (event.clientY - rect.top) * (rect.height ? height / rect.height : 1),
        };
    }

    function shouldSnapForEvent(event) {
        const base = state.chartView.magnetMode !== 'off';
        const invert = !!(event && (event.ctrlKey || event.metaKey));
        return invert ? !base : base;
    }

    function chartDomainPoint(point, layout, snap = false) {
        const rawIndex = layout.barIndexAtX(point.x);
        const minIndex = Math.min(0, Math.floor(layout.first));
        const maxIndex = Math.max(0, layout.allBars.length - 1, Math.ceil(layout.lastExclusive - 1));
        let domainPoint = {
            barIndex: clamp(rawIndex, minIndex, maxIndex),
            price: layout.priceAtY(point.y),
        };
        if (snap) domainPoint = snapDomainPoint(domainPoint, layout);
        return enrichDomainPointTime(domainPoint, layout);
    }

    function snapDomainPoint(domainPoint, layout) {
        if (state.chartView.magnetMode === 'off') return domainPoint;
        const barIndex = Math.round(domainPoint.barIndex);
        if (barIndex < 0 || barIndex >= layout.allBars.length) return domainPoint;
        const bar = layout.allBars[barIndex];
        if (!bar) return domainPoint;
        const prices = [bar.open, bar.high, bar.low, bar.close]
            .map(Number)
            .filter(Number.isFinite);
        if (!prices.length) return { ...domainPoint, barIndex };
        const price = prices.reduce((best, candidate) => (
            Math.abs(candidate - domainPoint.price) < Math.abs(best - domainPoint.price) ? candidate : best
        ), prices[0]);
        if (state.chartView.magnetMode === 'weak') {
            const range = Math.max(0.0000001, layout.hi - layout.lo);
            if (Math.abs(price - domainPoint.price) > range * 0.018) {
                return domainPoint;
            }
        }
        return { barIndex, price };
    }

    function enrichDomainPointTime(domainPoint, layout) {
        if (!domainPoint || !layout?.allBars?.length) return domainPoint;
        const rawBarIndex = Number(domainPoint.barIndex || 0);
        const barIndex = Math.round(rawBarIndex);
        if (barIndex < 0 || barIndex >= layout.allBars.length) {
            return {
                ...domainPoint,
                barIndex: rawBarIndex,
            };
        }
        const bar = layout.allBars[barIndex];
        const time = barTimeValue(bar);
        return {
            ...domainPoint,
            barIndex: rawBarIndex,
            ...(Number.isFinite(time) ? { time, time_iso: bar?.time_iso || bar?.time || null, sourceTimeframe: layout.data?.timeframe || state.activeMarketData?.timeframe || '' } : {}),
        };
    }

    function domainPointBarIndex(point, layout) {
        const value = point?.time ?? point?.time_iso;
        if ((value || value === 0) && layout?.allBars?.length) {
            return chartIndexForBarsTime(layout.allBars, value, point?.barIndex ?? 0);
        }
        return Number(point?.barIndex || 0);
    }

    function screenPointFromDomain(point, layout) {
        return {
            x: layout.x(domainPointBarIndex(point, layout)),
            y: layout.y(point.price),
        };
    }
    function createPositionDrawing(direction, domainPoint, layout) {
        const rr = defaultPositionRr();
        const visibleRange = Math.max(0.0000001, layout.hi - layout.lo);
        const riskDistance = visibleRange * 0.08;
        const entry = domainPoint.price;
        const stop = direction === 'long' ? entry - riskDistance : entry + riskDistance;
        const target = direction === 'long' ? entry + riskDistance * rr : entry - riskDistance * rr;
        const span = Math.max(14, Math.round(layout.visibleCount * 0.14));
        const startBar = clamp(domainPoint.barIndex - 2, 0, Math.max(0, layout.allBars.length - 1));
        const endBar = startBar + span;
        const startPoint = enrichDomainPointTime({ barIndex: startBar, price: entry }, layout);
        const endPoint = enrichDomainPointTime({ barIndex: endBar, price: entry }, layout);
        const drawing = {
            type: 'position',
            direction,
            entry,
            stop,
            target,
            startBar,
            endBar,
            startTime: startPoint?.time,
            startTimeIso: startPoint?.time_iso,
            endTime: endPoint?.time,
            endTimeIso: endPoint?.time_iso,
            color: direction === 'long' ? '#089981' : '#f23645',
        };
        getDrawingId(drawing);
        return drawing;
    }
    function defaultPositionRr() {
        const rr = Number(state.strategy?.risk_reward?.default_rr ?? state.strategy?.risk_reward?.min_rr ?? 3);
        return Number.isFinite(rr) && rr > 0 ? rr : 3;
    }

    function positionRewardRisk(drawing) {
        const risk = Math.abs(Number(drawing.entry) - Number(drawing.stop));
        const reward = Math.abs(Number(drawing.target) - Number(drawing.entry));
        return risk > 0 ? reward / risk : 0;
    }

    function positionBarIndex(drawing, which, layout) {
        const time = which === 'end'
            ? (drawing.endTime ?? drawing.endTimeIso)
            : (drawing.startTime ?? drawing.startTimeIso);
        const fallback = which === 'end' ? drawing.endBar : drawing.startBar;
        return (time || time === 0) ? chartIndexForBarsTime(layout.allBars || [], time, fallback || 0) : Number(fallback || 0);
    }

    function positionScreenBounds(drawing, layout) {
        const startBar = positionBarIndex(drawing, 'start', layout);
        const endBar = positionBarIndex(drawing, 'end', layout);
        const x1 = clamp(layout.x(startBar), layout.priceArea.left, layout.priceArea.right);
        const x2 = clamp(layout.x(endBar), layout.priceArea.left, layout.priceArea.right);
        const entryY = layout.y(drawing.entry);
        const stopY = layout.y(drawing.stop);
        const targetY = layout.y(drawing.target);
        const left = Math.min(x1, x2);
        const right = Math.max(x1, x2);
        return {
            left,
            right: Math.max(right, left + 24),
            top: Math.min(entryY, stopY, targetY),
            bottom: Math.max(entryY, stopY, targetY),
        };
    }
    function positionHandlePoints(drawing, layout) {
        const bounds = positionScreenBounds(drawing, layout);
        if (!bounds) return [];
        return [
            { handle: 'target', x: bounds.left, y: layout.y(drawing.target) },
            { handle: 'target', x: bounds.right, y: layout.y(drawing.target) },
            { handle: 'entry', x: bounds.left, y: layout.y(drawing.entry) },
            { handle: 'entry', x: bounds.right, y: layout.y(drawing.entry) },
            { handle: 'stop', x: bounds.left, y: layout.y(drawing.stop) },
            { handle: 'stop', x: bounds.right, y: layout.y(drawing.stop) },
        ];
    }

    function findEditablePosition(point, layout) {
        if (state.chartView.drawingsLocked) return null;
        const drawings = getChartDrawings(state.activeMarketData).filter((drawing) => drawing.type === 'position');
        let best = null;
        let bestDistance = Infinity;
        drawings.forEach((drawing) => {
            if (drawing.locked || drawing.hidden) return;
            const bounds = positionScreenBounds(drawing, layout);
            if (!bounds) return;
            const id = getDrawingId(drawing);
            positionHandlePoints(drawing, layout).forEach((handlePoint) => {
                const distance = Math.hypot(point.x - handlePoint.x, point.y - handlePoint.y);
                if (distance < bestDistance && distance <= 12) {
                    bestDistance = distance;
                    best = { id, handle: handlePoint.handle, drawing };
                }
            });

            const withinX = point.x >= bounds.left - 10 && point.x <= bounds.right + 10;
            const handles = [
                ['target', Math.abs(point.y - layout.y(drawing.target))],
                ['entry', Math.abs(point.y - layout.y(drawing.entry))],
                ['stop', Math.abs(point.y - layout.y(drawing.stop))],
            ];
            handles.forEach(([handle, distance]) => {
                if (withinX && distance < bestDistance && distance <= 8) {
                    bestDistance = distance;
                    best = { id, handle, drawing };
                }
            });
            if (!best && point.x >= bounds.left && point.x <= bounds.right && point.y >= bounds.top && point.y <= bounds.bottom) {
                best = { id, handle: 'move', drawing };
            }
        });
        return best;
    }

    function findEditableDrawing(point, layout) {
        if (state.chartView.drawingsLocked || !state.chartView.drawingsVisible) return null;
        const drawings = getChartDrawings(state.activeMarketData).filter((drawing) => drawing.type !== 'position' && !drawing.locked && !drawing.hidden);
        let best = null;
        let bestDistance = Infinity;
        drawings.forEach((drawing) => {
            const id = getDrawingId(drawing);
            if (BOX_TOOLS.has(drawing.type) && drawing.points?.length >= 2) {
                boxDrawingResizeHandles(drawing, layout).forEach((handlePoint) => {
                    const distance = Math.hypot(point.x - handlePoint.x, point.y - handlePoint.y);
                    if (distance < bestDistance && distance <= 10) {
                        bestDistance = distance;
                        best = { id, handle: handlePoint.handle, drawing };
                    }
                });
            }
            drawingAnchorPoints(drawing, layout).forEach((anchor, anchorIndex) => {
                const distance = Math.hypot(point.x - anchor.x, point.y - anchor.y);
                if (distance < bestDistance && distance <= 11) {
                    bestDistance = distance;
                    best = { id, handle: 'anchor', anchorIndex, drawing };
                }
            });
            const distance = drawingDistance(point, drawing, layout);
            const hasResizeHandle = best?.id === id && String(best?.handle || '').startsWith('resize-');
            if (!hasResizeHandle && distance < bestDistance && distance <= 18) {
                bestDistance = distance;
                best = { id, handle: 'move', drawing };
            }
        });
        return best;
    }

    function boxDrawingResizeHandles(drawing, layout) {
        if (!BOX_TOOLS.has(drawing.type) || drawing.points?.length < 2) return [];
        const bounds = drawingScreenBounds(drawing, layout);
        if (!bounds) return [];
        const rect = normalizeRect(bounds);
        const centerX = (rect.left + rect.right) / 2;
        const centerY = (rect.top + rect.bottom) / 2;
        return [
            { handle: 'resize-nw', x: rect.left, y: rect.top },
            { handle: 'resize-n', x: centerX, y: rect.top },
            { handle: 'resize-ne', x: rect.right, y: rect.top },
            { handle: 'resize-e', x: rect.right, y: centerY },
            { handle: 'resize-se', x: rect.right, y: rect.bottom },
            { handle: 'resize-s', x: centerX, y: rect.bottom },
            { handle: 'resize-sw', x: rect.left, y: rect.bottom },
            { handle: 'resize-w', x: rect.left, y: centerY },
        ];
    }

    function drawingEditCursor(handle) {
        const value = String(handle || '');
        if (value === 'move') return 'move';
        if (value.includes('nw') || value.includes('se')) return 'nwse-resize';
        if (value.includes('ne') || value.includes('sw')) return 'nesw-resize';
        if (value.includes('n') || value.includes('s') || ['entry', 'stop', 'target'].includes(value)) return 'ns-resize';
        if (value.includes('e') || value.includes('w')) return 'ew-resize';
        return 'crosshair';
    }
    function drawingAnchorPoints(drawing, layout) {
        if (Array.isArray(drawing.points)) return drawing.points.map((point) => screenPointFromDomain(point, layout));
        if (drawing.point) return [screenPointFromDomain(drawing.point, layout)];
        if (drawing.type === 'hline' || drawing.type === 'hray' || drawing.type === 'alert') {
            return [{ x: layout.priceArea.left + 12, y: layout.y(drawing.price) }];
        }
        if (drawing.type === 'vline') {
            return [{ x: layout.x(domainPointBarIndex(drawing, layout)), y: layout.priceArea.top + 20 }];
        }
        if (drawing.type === 'cross-line') {
            return [{ x: layout.x(domainPointBarIndex(drawing, layout)), y: layout.y(drawing.price) }];
        }
        return [];
    }

    function startDrawingEdit(editableDrawing, point, domainPoint) {
        pushDrawingHistory();
        state.chartView.selectedDrawingIds = [editableDrawing.id];
        state.chartView.selectionBox = null;
        state.chartView.drag = {
            mode: 'edit-drawing',
            id: editableDrawing.id,
            handle: editableDrawing.handle,
            anchorIndex: editableDrawing.anchorIndex,
            x: point.x,
            y: point.y,
            startDomain: domainPoint,
            drawing: cloneDrawing(editableDrawing.drawing),
        };
        $('#marketChart').style.cursor = editableDrawing.handle === 'move' ? 'move' : 'crosshair';
        drawMarketChart(state.activeMarketData);
    }

    function updatePositionDrawingDrag(drag, point, layout) {
        const drawing = findDrawingById(drag.id);
        const original = drag.drawing;
        if (!drawing || !original) return;

        const domainPoint = chartDomainPoint(point, layout, drag.handle !== 'move');
        const priceDelta = domainPoint.price - drag.startDomain.price;
        const barDelta = Math.round((point.x - drag.x) / Math.max(1, layout.barStep));

        if (drag.handle === 'move') {
            drawing.entry = original.entry + priceDelta;
            drawing.stop = original.stop + priceDelta;
            drawing.target = original.target + priceDelta;
            drawing.startBar = original.startBar + barDelta;
            drawing.endBar = original.endBar + barDelta;
            const startPoint = enrichDomainPointTime({ barIndex: drawing.startBar, price: drawing.entry }, layout);
            const endPoint = enrichDomainPointTime({ barIndex: drawing.endBar, price: drawing.entry }, layout);
            drawing.startTime = startPoint?.time;
            drawing.startTimeIso = startPoint?.time_iso;
            drawing.endTime = endPoint?.time;
            drawing.endTimeIso = endPoint?.time_iso;
            return;
        }

        if (drag.handle === 'entry') {
            drawing.entry = original.entry + priceDelta;
            drawing.stop = original.stop + priceDelta;
            drawing.target = original.target + priceDelta;
            return;
        }

        if (drag.handle === 'stop') {
            drawing.stop = clampPositionStop(drawing.direction, domainPoint.price, drawing.entry, layout);
            return;
        }

        if (drag.handle === 'target') {
            drawing.target = clampPositionTarget(drawing.direction, domainPoint.price, drawing.entry, layout);
        }
    }

    function updateGenericDrawingDrag(drag, point, layout) {
        const drawing = findDrawingById(drag.id);
        const original = drag.drawing;
        if (!drawing || !original) return;
        const domainPoint = chartDomainPoint(point, layout, shouldSnapForEvent(null));
        const priceDelta = domainPoint.price - drag.startDomain.price;
        const barDelta = Math.round((point.x - drag.x) / Math.max(1, layout.barStep));

        if (String(drag.handle || '').startsWith('resize-')) {
            resizeBoxDrawingByHandle(drawing, drag, point, layout);
            return;
        }

        if (drag.handle === 'anchor') {
            setDrawingAnchor(drawing, drag.anchorIndex, domainPoint);
            return;
        }
        moveDrawingByDelta(drawing, original, barDelta, priceDelta);
    }

    function resizeBoxDrawingByHandle(drawing, drag, point, layout) {
        const original = drag.drawing;
        if (!BOX_TOOLS.has(drawing.type) || !Array.isArray(original?.points) || original.points.length < 2) return;
        const originalBounds = drawingScreenBounds(original, layout);
        if (!originalBounds) return;
        const rect = normalizeRect(originalBounds);
        const handle = String(drag.handle || '').replace('resize-', '');
        const minSize = 10;
        let left = rect.left;
        let right = rect.right;
        let top = rect.top;
        let bottom = rect.bottom;

        if (handle.includes('w')) left = clamp(point.x, layout.priceArea.left, right - minSize);
        if (handle.includes('e')) right = clamp(point.x, left + minSize, layout.priceArea.right);
        if (handle.includes('n')) top = clamp(point.y, layout.priceArea.top, bottom - minSize);
        if (handle.includes('s')) bottom = clamp(point.y, top + minSize, layout.panelBottom);

        drawing.points = [
            chartDomainPoint({ x: left, y: top }, layout, false),
            chartDomainPoint({ x: right, y: bottom }, layout, false),
        ];
    }

    function setDrawingAnchor(drawing, anchorIndex, domainPoint) {
        if (Array.isArray(drawing.points) && drawing.points[anchorIndex]) {
            drawing.points[anchorIndex] = domainPoint;
        } else if (drawing.point) {
            drawing.point = domainPoint;
        } else if (drawing.type === 'hline' || drawing.type === 'hray' || drawing.type === 'alert') {
            drawing.price = domainPoint.price;
            if (drawing.type === 'hray') {
                drawing.startBar = domainPoint.barIndex;
                drawing.startTime = domainPoint.time;
                drawing.startTimeIso = domainPoint.time_iso;
            }
        } else if (drawing.type === 'vline') {
            drawing.barIndex = domainPoint.barIndex;
            drawing.time = domainPoint.time;
            drawing.time_iso = domainPoint.time_iso;
            drawing.sourceTimeframe = domainPoint.sourceTimeframe;
        } else if (drawing.type === 'cross-line') {
            drawing.barIndex = domainPoint.barIndex;
            drawing.time = domainPoint.time;
            drawing.time_iso = domainPoint.time_iso;
            drawing.sourceTimeframe = domainPoint.sourceTimeframe;
            drawing.price = domainPoint.price;
        }
    }

    function moveDrawingByDelta(drawing, original, barDelta, priceDelta) {
        const shiftPoint = (point) => enrichDomainPointTime({
            ...point,
            barIndex: Number(point.barIndex || 0) + barDelta,
            price: Number(point.price || 0) + priceDelta,
        }, state.chartView.lastLayout);
        if (Array.isArray(original.points)) drawing.points = original.points.map(shiftPoint);
        if (original.point) drawing.point = shiftPoint(original.point);
        if (Number.isFinite(Number(original.price))) drawing.price = Number(original.price) + priceDelta;
        if (Number.isFinite(Number(original.barIndex))) {
            drawing.barIndex = Number(original.barIndex) + barDelta;
            const movedPoint = enrichDomainPointTime({ barIndex: drawing.barIndex, price: drawing.price ?? original.price ?? 0 }, state.chartView.lastLayout);
            drawing.time = movedPoint?.time;
            drawing.time_iso = movedPoint?.time_iso;
            drawing.sourceTimeframe = movedPoint?.sourceTimeframe;
        }
        if (Number.isFinite(Number(original.startBar))) {
            drawing.startBar = Number(original.startBar) + barDelta;
            const startPoint = enrichDomainPointTime({ barIndex: drawing.startBar, price: drawing.entry ?? drawing.price ?? 0 }, state.chartView.lastLayout);
            drawing.startTime = startPoint?.time;
            drawing.startTimeIso = startPoint?.time_iso;
        }
        if (Number.isFinite(Number(original.endBar))) {
            drawing.endBar = Number(original.endBar) + barDelta;
            const endPoint = enrichDomainPointTime({ barIndex: drawing.endBar, price: drawing.entry ?? drawing.price ?? 0 }, state.chartView.lastLayout);
            drawing.endTime = endPoint?.time;
            drawing.endTimeIso = endPoint?.time_iso;
        }
    }
    function clampPositionStop(direction, price, entry, layout) {
        const minDistance = Math.max(0.0000001, (layout.hi - layout.lo) * 0.005);
        return direction === 'long'
            ? Math.min(price, entry - minDistance)
            : Math.max(price, entry + minDistance);
    }

    function clampPositionTarget(direction, price, entry, layout) {
        const minDistance = Math.max(0.0000001, (layout.hi - layout.lo) * 0.005);
        return direction === 'long'
            ? Math.max(price, entry + minDistance)
            : Math.min(price, entry - minDistance);
    }

    function findDrawingById(id) {
        return getChartDrawings(state.activeMarketData).find((drawing) => getDrawingId(drawing) === id) || null;
    }

    function cloneDrawing(drawing) {
        return JSON.parse(JSON.stringify(drawing));
    }

    function getDrawingId(drawing) {
        if (!drawing.id) {
            drawing.id = `drawing_${Date.now()}_${Math.random().toString(16).slice(2)}`;
        }
        return drawing.id;
    }

    function selectNearestDrawing(point, layout, maxDistance = 22) {
        if (!state.chartView.drawingsVisible) return null;
        const drawings = getChartDrawings(state.activeMarketData);
        let bestId = null;
        let bestDistance = Infinity;
        drawings.forEach((drawing) => {
            const distance = drawingDistance(point, drawing, layout);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestId = getDrawingId(drawing);
            }
        });
        return bestDistance <= maxDistance ? bestId : null;
    }

    function selectDrawingsInBox(box, layout) {
        if (!state.chartView.drawingsVisible) return [];
        if (!box) return [];
        const a = screenPointFromDomain(box.start, layout);
        const b = screenPointFromDomain(box.end, layout);
        const selection = normalizeRect({
            left: Math.min(a.x, b.x),
            right: Math.max(a.x, b.x),
            top: Math.min(a.y, b.y),
            bottom: Math.max(a.y, b.y),
        });
        const isTiny = selection.right - selection.left < 8 && selection.bottom - selection.top < 8;
        const crossing = b.x < a.x;
        const drawings = getChartDrawings(state.activeMarketData);
        if (isTiny) {
            const center = { x: (selection.left + selection.right) / 2, y: (selection.top + selection.bottom) / 2 };
            const id = selectNearestDrawing(center, layout, 22);
            return id ? [id] : [];
        }
        return drawings
            .filter((drawing) => {
                if (drawing.hidden) return false;
                const bounds = drawingScreenBounds(drawing, layout);
                if (!bounds) return false;
                const rect = normalizeRect(bounds);
                return crossing ? rectsIntersect(selection, rect) : rectContainsRect(selection, rect);
            })
            .map((drawing) => getDrawingId(drawing));
    }

    function drawingScreenBounds(drawing, layout) {
        if (drawing.type === 'hline' || drawing.type === 'alert') {
            const yy = layout.y(drawing.price);
            return {
                left: layout.priceArea.left,
                right: layout.priceArea.right,
                top: yy - 3,
                bottom: yy + 3,
            };
        }
        if (drawing.type === 'vline') {
            const xx = layout.x(domainPointBarIndex(drawing, layout));
            return {
                left: xx - 3,
                right: xx + 3,
                top: layout.priceArea.top,
                bottom: layout.panelBottom,
            };
        }
        if (drawing.type === 'cross-line') {
            const xx = layout.x(domainPointBarIndex(drawing, layout));
            const yy = layout.y(drawing.price);
            return {
                left: layout.priceArea.left,
                right: layout.priceArea.right,
                top: Math.min(layout.priceArea.top, yy - 3),
                bottom: layout.panelBottom,
                anchorX: xx,
            };
        }
        if (TEXT_TOOLS.has(drawing.type) || drawing.type === 'price-label') {
            const point = screenPointFromDomain(drawing.point || drawing.points?.[0] || { barIndex: 0, price: layout.hi }, layout);
            const text = String(drawing.text || 'Ghi chú').slice(0, 80);
            const width = Math.min(260, Math.max(52, text.length * 7 + 16));
            return {
                left: point.x,
                right: point.x + width,
                top: point.y - 26,
                bottom: point.y,
            };
        }
        if (['dot', 'pin', 'flag', 'icon', 'arrow-marker', 'up-arrow', 'down-arrow'].includes(drawing.type)) {
            const point = screenPointFromDomain(drawing.point || { barIndex: 0, price: layout.hi }, layout);
            return {
                left: point.x - 12,
                right: point.x + 12,
                top: point.y - 16,
                bottom: point.y + 12,
            };
        }
        if (drawing.points?.length >= 2) {
            const a = screenPointFromDomain(drawing.points[0], layout);
            const b = screenPointFromDomain(drawing.points[1], layout);
            if (drawing.type === 'ray') {
                const end = rayEndPoint(a, b, layout);
                return {
                    left: Math.min(a.x, end.x),
                    right: Math.max(a.x, end.x),
                    top: Math.min(a.y, end.y),
                    bottom: Math.max(a.y, end.y),
                };
            }
            return {
                left: Math.min(a.x, b.x),
                right: Math.max(a.x, b.x),
                top: Math.min(a.y, b.y),
                bottom: Math.max(a.y, b.y),
            };
        }
        if (drawing.type === 'position') {
            return positionScreenBounds(drawing, layout);
        }
        return null;
    }

    function normalizeRect(rect) {
        return {
            left: Math.min(rect.left, rect.right),
            right: Math.max(rect.left, rect.right),
            top: Math.min(rect.top, rect.bottom),
            bottom: Math.max(rect.top, rect.bottom),
        };
    }

    function rectsIntersect(a, b) {
        return a.left <= b.right && a.right >= b.left && a.top <= b.bottom && a.bottom >= b.top;
    }

    function rectContainsRect(a, b) {
        return a.left <= b.left && a.right >= b.right && a.top <= b.top && a.bottom >= b.bottom;
    }

    function distanceToSegment(p, a, b) {
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        if (dx === 0 && dy === 0) return Math.hypot(p.x - a.x, p.y - a.y);
        const t = clamp(((p.x - a.x) * dx + (p.y - a.y) * dy) / (dx * dx + dy * dy), 0, 1);
        return Math.hypot(p.x - (a.x + t * dx), p.y - (a.y + t * dy));
    }

    function drawLevel(ctx, y, plot, width, value, color, label, symbol) {
        const price = Number(value);
        if (!Number.isFinite(price)) return;
        const yy = y(price);
        ctx.strokeStyle = color;
        ctx.setLineDash([6, 5]);
        ctx.beginPath();
        ctx.moveTo(plot.left, yy);
        ctx.lineTo(width - plot.right, yy);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = color;
        ctx.fillText(`${label} ${formatPrice(price, symbol)}`, plot.left + 8, yy - 6);
    }

    function buildCandles(c) {
        const entry = Number(c.entry) || 1;
        const sl = Number(c.sl) || entry * 1.002;
        const tp = Number(c.selected_tp) || entry * 0.998;
        const direction = String(c.direction || 'sell').toLowerCase();
        const spread = Math.max(Math.abs(sl - entry), Math.abs(tp - entry), entry * 0.002);
        const candles = [];
        let close = direction === 'sell' ? entry - spread * 1.7 : entry + spread * 1.7;

        for (let i = 0; i < 44; i++) {
            const phase = i / 43;
            let target;
            if (phase < 0.42) {
                target = direction === 'sell'
                    ? entry - spread * 1.5 + phase * spread * 3.2
                    : entry + spread * 1.5 - phase * spread * 3.2;
            } else if (phase < 0.62) {
                target = entry + (Math.sin(phase * 36) * spread * 0.18);
            } else {
                const travel = (phase - 0.62) / 0.38;
                target = direction === 'sell'
                    ? entry - travel * Math.abs(entry - tp) * 0.92
                    : entry + travel * Math.abs(tp - entry) * 0.92;
            }
            const open = close;
            close = target + Math.sin(i * 1.7) * spread * 0.08;
            const high = Math.max(open, close) + spread * (0.08 + (i % 3) * 0.025);
            const low = Math.min(open, close) - spread * (0.08 + (i % 4) * 0.02);
            candles.push({ open, high, low, close });
        }
        return candles;
    }

    function getChartAnalytics(data) {
        const bars = data?.bars || [];
        const lastTime = bars[bars.length - 1]?.time || bars[bars.length - 1]?.time_iso || '';
        const serverRunKey = (state.backtestRuns || [])
            .map((run) => `${run.backtestRunId || run.id || ''}:${(run.trades || []).length}`)
            .join('|');
        const key = `${chartDataKey(data)}_${bars.length}_${lastTime}_${serverRunKey}`;
        if (state.chartView.analyticsCache[key]) return state.chartView.analyticsCache[key];

        const indicators = buildChartIndicators(bars);
        const serverRuns = serverBacktestRunsForChart(data);
        const runs = [
            ...serverRuns,
            ...BACKTEST_RUNS.map((config) => simulateBacktestRun(bars, indicators, config, data?.symbol || '')),
        ];
        const analytics = {
            key,
            indicators,
            warnings: buildDataWarnings(bars),
            runs,
        };
        state.chartView.analyticsCache[key] = analytics;
        return analytics;
    }

    function serverBacktestRunsForChart(data) {
        const symbol = String(data?.symbol || '').toUpperCase();
        const seen = new Set();
        return (state.backtestRuns || [])
            .filter((run) => {
                const runId = run.backtestRunId || run.id;
                if (!runId || seen.has(runId)) return false;
                seen.add(runId);
                return String(run.symbol || run.summary?.symbol || '').toUpperCase() === symbol;
            })
            .map((run) => serverRunToChartRun(run, data));
    }

    function serverRunToChartRun(run, data = null) {
        const summary = run.summary || run;
        const trades = (run.trades || [])
            .filter((trade) => trade.result !== 'SKIPPED')
            .map((trade) => mapServerTradeToChartTrade(trade, data))
            .filter(Boolean);
        return {
            id: run.backtestRunId || run.id,
            name: `Backtest ${String(summary.timeframe || '')} - ${formatDisplayLabel(summary.dataSplit || summary.rangeType || '', 'dataSplits')}`.trim(),
            color: '#1f6feb',
            trades,
            equityCurve: run.equityCurve || [],
            drawdownCurve: [],
            stats: summary.stats || {
                trades: Number(summary.totalTrades || trades.length),
                wins: Number(summary.winTrades || 0),
                losses: Number(summary.lossTrades || 0),
                winRate: Number(summary.winRate || 0),
                totalR: Number(summary.netProfitR || 0),
                profitFactor: Number(summary.profitFactor || 0),
                expectancy: Number(summary.averageRR || 0),
                maxDrawdownPct: -1 * Number(summary.maxDrawdownPercent || 0),
                finalEquity: Number(summary.finalBalance || 0),
                returnPct: Number(summary.netProfitPercent || 0),
            },
            debug: run.debug || null,
            summary,
        };
    }

    function mapServerTradeToChartTrade(trade, data = null) {
        const bars = data?.bars || [];
        const sourceEntryIndex = Number(trade.entryIndex ?? trade.signalIndex ?? 0);
        const sourceExitIndex = Number(trade.exitIndex ?? trade.closeIndex ?? sourceEntryIndex);
        let entryIndex = sourceEntryIndex;
        let exitIndex = sourceExitIndex;
        if (bars.length) {
            entryIndex = chartIndexForBarsTime(bars, trade.entryTime || trade.openTime || trade.time, sourceEntryIndex);
            exitIndex = chartIndexForBarsTime(bars, trade.exitTime || trade.closeTime || trade.entryTime || trade.time, sourceExitIndex);
        }
        entryIndex = clamp(Math.round(Number(entryIndex || 0)), 0, Math.max(0, bars.length - 1));
        exitIndex = clamp(Math.round(Number(exitIndex || entryIndex)), entryIndex, Math.max(entryIndex, bars.length - 1));
        return {
            ...trade,
            sourceEntryIndex,
            sourceExitIndex,
            entryIndex,
            exitIndex,
            direction: trade.chartDirection || (trade.direction === 'BUY' ? 'long' : trade.direction === 'SELL' ? 'short' : trade.direction),
            originalDirection: trade.direction,
            entryReason: trade.entryReason || trade.reasonEnter || '',
            exitReason: trade.exitReason || trade.reasonExit || '',
            initialStop: trade.initialStop ?? trade.stopLoss,
            targetPrice: trade.targetPrice ?? trade.takeProfit,
            netR: Number(trade.netR ?? trade.rrResult ?? 0),
            pnlUsd: Number(trade.pnlUsd ?? trade.profitLoss ?? 0),
        };
    }

    function chartIndexForBarsTime(bars, value, fallback = 0) {
        const ts = timeToSeconds(value);
        if (!Number.isFinite(ts) || !bars.length) return Number.isFinite(Number(fallback)) ? Number(fallback) : 0;
        let best = 0;
        let bestDistance = Infinity;
        bars.forEach((bar, index) => {
            const diff = Math.abs(barTimeValue(bar) - ts);
            if (diff < bestDistance) {
                bestDistance = diff;
                best = index;
            }
        });
        return best;
    }

    function chartIndexAtOrBeforeTime(bars, value, fallback = 0) {
        const ts = timeToSeconds(value);
        if (!Number.isFinite(ts) || !bars.length) return Number.isFinite(Number(fallback)) ? Number(fallback) : 0;
        let low = 0;
        let high = bars.length - 1;
        let best = 0;
        while (low <= high) {
            const mid = (low + high) >> 1;
            const barTime = barTimeValue(bars[mid]);
            if (!Number.isFinite(barTime) || barTime <= ts) {
                best = mid;
                low = mid + 1;
            } else {
                high = mid - 1;
            }
        }
        return best;
    }
    function buildChartIndicators(bars) {
        const closes = bars.map((bar) => Number(bar.close));
        const highs = bars.map((bar) => Number(bar.high));
        const lows = bars.map((bar) => Number(bar.low));
        const volumes = bars.map((bar) => Number(bar.tick_volume || bar.real_volume || 0));
        const ema20 = ema(closes, 20);
        const ema50 = ema(closes, 50);
        const ema200 = ema(closes, 200);
        const atr14 = atr(bars, 14);
        const macd = macdSeries(closes, 12, 26, 9);
        const bb = bollingerBands(closes, 20, 2);
        return {
            closes,
            highs,
            lows,
            volumes,
            ema20,
            ema50,
            ema200,
            sma100: sma(closes, 100),
            rsi: rsi(closes, 14),
            atr14,
            bbUpper: bb.upper,
            bbMiddle: bb.middle,
            bbLower: bb.lower,
            vwap: vwap(bars),
            atrStop: atrStop(closes, ema50, atr14, 2.2),
            volumeMa: sma(volumes, 20),
            macdLine: macd.line,
            macdSignal: macd.signal,
            macdHist: macd.hist,
        };
    }

    function simulateBacktestRun(bars, indicators, config, symbol) {
        const closes = indicators.closes;
        const fast = ema(closes, config.fast);
        const slow = ema(closes, config.slow);
        const trades = [];
        const equityCurve = [];
        const drawdownCurve = [];
        const initialEquity = 10000;
        const riskUsd = 100;
        const feeR = 0.03;
        const slippageR = 0.02;
        let equity = initialEquity;
        let peak = initialEquity;
        let open = null;
        const start = Math.max(config.slow + 3, 30);

        for (let i = start; i < bars.length; i++) {
            const bar = bars[i];
            if (!bar || !Number.isFinite(closes[i])) continue;

            if (open) {
                const exit = manageOpenBacktestTrade(open, bars, indicators, fast, slow, config, i);
                if (exit) {
                    const trade = closeBacktestTrade(open, exit, bars, riskUsd, feeR, slippageR, config, symbol);
                    equity += trade.pnlUsd;
                    peak = Math.max(peak, equity);
                    trade.equityAfter = equity;
                    trade.drawdownPct = peak > 0 ? ((equity - peak) / peak) * 100 : 0;
                    trades.push(trade);
                    equityCurve.push({ index: trade.exitIndex, equity });
                    drawdownCurve.push({ index: trade.exitIndex, drawdownPct: trade.drawdownPct });
                    open = null;
                }
            }

            if (!open) {
                const signal = backtestSignal(fast, slow, i);
                if (!signal) continue;
                const atrValue = indicators.atr14[i];
                if (!Number.isFinite(atrValue) || atrValue <= 0) continue;
                const entryPrice = Number(bar.close);
                const riskDistance = Math.max(atrValue * 1.6, Math.abs(entryPrice) * 0.00035);
                const direction = signal === 'long' ? 'long' : 'short';
                const initialStop = direction === 'long' ? entryPrice - riskDistance : entryPrice + riskDistance;
                const targetPrice = direction === 'long' ? entryPrice + riskDistance * config.rr : entryPrice - riskDistance * config.rr;
                open = {
                    id: `${config.id}_${trades.length + 1}`,
                    runId: config.id,
                    symbol,
                    direction,
                    entryIndex: i,
                    entryTime: bar.time_iso || bar.time || '',
                    entryPrice,
                    initialStop,
                    stopPrice: initialStop,
                    targetPrice,
                    riskDistance,
                    rr: config.rr,
                    entryReason: `${config.name}: EMA ${config.fast} cắt EMA ${config.slow}`,
                    mfeR: 0,
                    maeR: 0,
                    beIndex: null,
                    trailIndex: null,
                };
            }
        }

        if (open && bars.length) {
            const lastIndex = bars.length - 1;
            const trade = closeBacktestTrade(
                open,
                {
                    index: lastIndex,
                    price: Number(bars[lastIndex].close),
                    type: 'TIME',
                    reason: 'Hết dữ liệu kiểm thử',
                },
                bars,
                riskUsd,
                feeR,
                slippageR,
                config,
                symbol
            );
            equity += trade.pnlUsd;
            peak = Math.max(peak, equity);
            trade.equityAfter = equity;
            trade.drawdownPct = peak > 0 ? ((equity - peak) / peak) * 100 : 0;
            trades.push(trade);
            equityCurve.push({ index: trade.exitIndex, equity });
            drawdownCurve.push({ index: trade.exitIndex, drawdownPct: trade.drawdownPct });
        }

        return {
            ...config,
            trades,
            equityCurve,
            drawdownCurve,
            stats: backtestStats(trades, equityCurve, drawdownCurve, initialEquity),
        };
    }

    function manageOpenBacktestTrade(open, bars, indicators, fast, slow, config, i) {
        const bar = bars[i];
        const high = Number(bar.high);
        const low = Number(bar.low);
        const close = Number(bar.close);
        if (![high, low, close].every(Number.isFinite)) return null;
        const isLong = open.direction === 'long';
        const favorableR = isLong ? (high - open.entryPrice) / open.riskDistance : (open.entryPrice - low) / open.riskDistance;
        const adverseR = isLong ? (open.entryPrice - low) / open.riskDistance : (high - open.entryPrice) / open.riskDistance;
        open.mfeR = Math.max(open.mfeR, favorableR);
        open.maeR = Math.max(open.maeR, adverseR);

        const beTrigger = Number(state.strategy?.management?.be_trigger_r_min ?? 1.8);
        if (!open.beIndex && favorableR >= beTrigger) {
            open.stopPrice = open.entryPrice;
            open.beIndex = i;
        }

        const atrValue = indicators.atr14[i];
        if (Number.isFinite(atrValue) && favorableR >= 2.2) {
            const trail = isLong ? close - atrValue * 1.45 : close + atrValue * 1.45;
            const improves = isLong ? trail > open.stopPrice : trail < open.stopPrice;
            if (improves) {
                open.stopPrice = trail;
                open.trailIndex = i;
            }
        }

        const hitStop = isLong ? low <= open.stopPrice : high >= open.stopPrice;
        const hitTarget = isLong ? high >= open.targetPrice : low <= open.targetPrice;
        if (hitStop) {
            const beExit = Math.abs(open.stopPrice - open.entryPrice) < open.riskDistance * 0.02;
            return {
                index: i,
                price: open.stopPrice,
                type: beExit ? 'BE' : open.trailIndex ? 'TRAIL' : 'SL',
                reason: beExit ? 'Dời SL về giá vào rồi bị chạm' : open.trailIndex ? 'Chạm dừng kéo theo' : 'Chạm dừng lỗ',
            };
        }
        if (hitTarget) {
            return { index: i, price: open.targetPrice, type: 'TP', reason: 'Chạm take profit' };
        }

        const opposite = backtestSignal(fast, slow, i);
        if ((isLong && opposite === 'short') || (!isLong && opposite === 'long')) {
            return { index: i, price: close, type: 'SIGNAL', reason: 'EMA đảo tín hiệu' };
        }
        if (i - open.entryIndex >= config.maxBars) {
            return { index: i, price: close, type: 'TIME', reason: 'Quá thời gian giữ lệnh' };
        }
        return null;
    }

    function closeBacktestTrade(open, exit, bars, riskUsd, feeR, slippageR, config, symbol) {
        const isLong = open.direction === 'long';
        const grossR = isLong
            ? (exit.price - open.entryPrice) / open.riskDistance
            : (open.entryPrice - exit.price) / open.riskDistance;
        const netR = grossR - feeR - slippageR;
        return {
            id: open.id,
            runId: config.id,
            symbol,
            direction: open.direction,
            entryIndex: open.entryIndex,
            exitIndex: exit.index,
            entryTime: open.entryTime,
            exitTime: bars[exit.index]?.time_iso || bars[exit.index]?.time || '',
            entryPrice: open.entryPrice,
            exitPrice: exit.price,
            initialStop: open.initialStop,
            targetPrice: open.targetPrice,
            stopPrice: open.stopPrice,
            rr: open.rr,
            exitType: exit.type,
            entryReason: open.entryReason,
            exitReason: exit.reason,
            grossR,
            feeR,
            slippageR,
            netR,
            pnlUsd: netR * riskUsd,
            mfeR: open.mfeR,
            maeR: open.maeR,
            holdingBars: exit.index - open.entryIndex,
            beIndex: open.beIndex,
            trailIndex: open.trailIndex,
            result: netR > 0.05 ? 'win' : netR < -0.05 ? 'loss' : 'flat',
        };
    }

    function backtestSignal(fast, slow, index) {
        const f0 = fast[index - 1];
        const s0 = slow[index - 1];
        const f1 = fast[index];
        const s1 = slow[index];
        if (![f0, s0, f1, s1].every(Number.isFinite)) return null;
        if (f0 <= s0 && f1 > s1) return 'long';
        if (f0 >= s0 && f1 < s1) return 'short';
        return null;
    }

    function backtestStats(trades, equityCurve, drawdownCurve, initialEquity) {
        const wins = trades.filter((trade) => trade.netR > 0);
        const losses = trades.filter((trade) => trade.netR < 0);
        const totalR = trades.reduce((sum, trade) => sum + trade.netR, 0);
        const grossWin = wins.reduce((sum, trade) => sum + trade.netR, 0);
        const grossLoss = Math.abs(losses.reduce((sum, trade) => sum + trade.netR, 0));
        const lastEquity = equityCurve[equityCurve.length - 1]?.equity ?? initialEquity;
        return {
            trades: trades.length,
            wins: wins.length,
            losses: losses.length,
            winRate: trades.length ? (wins.length / trades.length) * 100 : 0,
            totalR,
            profitFactor: grossLoss > 0 ? grossWin / grossLoss : grossWin > 0 ? Infinity : 0,
            expectancy: trades.length ? totalR / trades.length : 0,
            maxDrawdownPct: Math.min(0, ...drawdownCurve.map((point) => point.drawdownPct)),
            finalEquity: lastEquity,
            returnPct: ((lastEquity - initialEquity) / initialEquity) * 100,
            avgWinR: wins.length ? grossWin / wins.length : 0,
            avgLossR: losses.length ? grossLoss / losses.length : 0,
        };
    }

    function buildDataWarnings(bars) {
        const warnings = [];
        const intervals = [];
        const ranges = [];
        const spreads = [];
        for (let i = 1; i < bars.length; i++) {
            const prevTime = barTimeValue(bars[i - 1]);
            const time = barTimeValue(bars[i]);
            if (Number.isFinite(prevTime) && Number.isFinite(time)) intervals.push(time - prevTime);
        }
        bars.forEach((bar) => {
            const high = Number(bar.high);
            const low = Number(bar.low);
            const spread = Number(bar.spread);
            if (Number.isFinite(high) && Number.isFinite(low)) ranges.push(Math.abs(high - low));
            if (Number.isFinite(spread) && spread > 0) spreads.push(spread);
        });
        const interval = median(intervals.filter((value) => value > 0));
        const normalRange = median(ranges.filter((value) => value > 0));
        const normalSpread = median(spreads.filter((value) => value > 0));
        let cap = 0;
        for (let i = 0; i < bars.length && cap < 160; i++) {
            const bar = bars[i];
            const volume = Number(bar.tick_volume || bar.real_volume || 0);
            const range = Math.abs(Number(bar.high) - Number(bar.low));
            const spread = Number(bar.spread || 0);
            if (i > 0 && interval > 0) {
                const diff = barTimeValue(bar) - barTimeValue(bars[i - 1]);
                if (Number.isFinite(diff) && diff > interval * 2.5) {
                    warnings.push({ index: i, type: 'gap', label: 'Gap dữ liệu', detail: `Khoảng trống ${formatDuration(diff)}` });
                    cap++;
                }
            }
            if (volume === 0) {
                warnings.push({ index: i, type: 'zero_volume', label: 'Khối lượng bằng 0', detail: formatChartTime(bar.time_iso || bar.time) });
                cap++;
            }
            if (normalRange > 0 && range > normalRange * 5) {
                warnings.push({ index: i, type: 'abnormal_range', label: 'Nến bất thường', detail: `Biên độ lớn hơn bình thường ${formatNumberValue(range / normalRange)} lần` });
                cap++;
            }
            if (normalSpread > 0 && spread > normalSpread * 3) {
                warnings.push({ index: i, type: 'high_spread', label: 'Spread cao', detail: `Spread ${formatNumberValue(spread)}` });
                cap++;
            }
        }
        return warnings;
    }

    function renderBacktestPanels(analytics) {
        renderChartControlState();
        const run = activeBacktestRun(analytics);
        renderBacktestSummary(run);
        renderTradeDetail(run);
        renderDataWarningsPanel(analytics);
        renderBacktestTradeList(run);
        renderChartBacktestSidebar(run);
    }

    function renderBacktestSummary(run) {
        const target = $('#backtestSummary');
        if (!target) return;
        if (!run) {
            target.innerHTML = '';
            return;
        }
        const stats = run.stats;
        target.innerHTML = [
            backtestMetricHtml('Số lệnh', number(stats.trades)),
            backtestMetricHtml('Tỷ lệ thắng', `${formatFixed(stats.winRate, 1)}%`),
            backtestMetricHtml('Lãi/lỗ R', formatSigned(stats.totalR, 2)),
            backtestMetricHtml('Hệ số lợi nhuận', Number.isFinite(stats.profitFactor) ? formatFixed(stats.profitFactor, 2) : '∞'),
            backtestMetricHtml('Sụt giảm tối đa', `${formatFixed(stats.maxDrawdownPct, 2)}%`),
            backtestMetricHtml('Kỳ vọng', `${formatSigned(stats.expectancy, 2)}R`),
        ].join('');
    }

    function renderTradeDetail(run) {
        const target = $('#tradeDetail');
        if (!target) return;
        const trade = selectedTrade(run);
        if (!trade) {
            target.innerHTML = `
                <div class="backtest-detail-head">
                    <strong>Chi tiết lệnh</strong>
                    <span>Chưa có lệnh</span>
                </div>
                <div class="notice">Chưa có lệnh kiểm thử phù hợp với bộ lọc hiện tại.</div>
            `;
            return;
        }
        target.innerHTML = `
            <div class="backtest-detail-head">
                <strong>Chi tiết lệnh</strong>
                <span>${escapeHtml(displayTradeSide(trade.direction))} | ${escapeHtml(displayExitType(trade.exitType))}</span>
            </div>
            <div class="trade-detail-grid">
                ${tradeDetailRow('Giá vào', `${formatPrice(trade.entryPrice, trade.symbol)} | ${formatChartTime(trade.entryTime)}`)}
                ${tradeDetailRow('Giá thoát', `${formatPrice(trade.exitPrice, trade.symbol)} | ${formatChartTime(trade.exitTime)}`)}
                ${tradeDetailRow('SL / TP', `${formatPrice(trade.initialStop, trade.symbol)} / ${formatPrice(trade.targetPrice, trade.symbol)}`)}
                ${tradeDetailRow('Lãi/lỗ ròng', `${formatSigned(trade.netR, 2)}R | ${money(trade.pnlUsd)}`)}
                ${tradeDetailRow('Phí / trượt giá', `${formatFixed(trade.feeR, 2)}R / ${formatFixed(trade.slippageR, 2)}R`)}
                ${tradeDetailRow('MFE / MAE', `${formatFixed(trade.mfeR, 2)}R / ${formatFixed(trade.maeR, 2)}R`)}
                ${tradeDetailRow('Thời gian giữ', `${number(trade.holdingBars)} nến`)}
                ${tradeDetailRow('Lý do vào', trade.entryReason)}
                ${tradeDetailRow('Lý do thoát', trade.exitReason)}
            </div>
            <div class="trade-detail-actions">
                <button class="chart-control" id="tradeFocusBtn" type="button">Tập trung</button>
                <button class="chart-control" id="tradeCopyBtn" type="button">Sao chép</button>
                <button class="chart-control" id="tradeExportBtn" type="button">Xuất JSON</button>
            </div>
        `;
        $('#tradeFocusBtn')?.addEventListener('click', () => focusTrade(trade));
        $('#tradeCopyBtn')?.addEventListener('click', () => copyTradeInfo(trade));
        $('#tradeExportBtn')?.addEventListener('click', () => exportTradeJson(trade));
    }

    function renderDataWarningsPanel(analytics) {
        const target = $('#dataWarningPanel');
        if (!target) return;
        const warnings = analytics.warnings.slice(0, 12);
        target.innerHTML = `
            <div class="backtest-detail-head">
                <strong>Cảnh báo dữ liệu</strong>
                <span>${number(analytics.warnings.length)}</span>
            </div>
            <div class="data-warning-list">
                ${
                    warnings.length
                        ? warnings
                              .map((warning) => `
                                  <div class="data-warning-item">
                                      <span>${escapeHtml(warning.label)}</span>
                                      <strong>${escapeHtml(warning.detail)}</strong>
                                  </div>
                              `)
                              .join('')
                        : '<div class="notice">Không phát hiện lỗi dữ liệu rõ ràng.</div>'
                }
            </div>
        `;
    }

    function renderBacktestTradeList(run) {
        const list = $('#backtestTradeList');
        if (!list) return;
        const trades = run ? filteredTrades(run.trades) : [];
        $('#tradeListCount').textContent = number(trades.length);
        if (!trades.length) {
            list.innerHTML = '<div class="notice">Không có lệnh nào trong bộ lọc hiện tại.</div>';
            return;
        }
        list.innerHTML = trades
            .slice()
            .reverse()
            .map((trade) => {
                const active = trade.id === state.chartView.selectedTradeId ? ' is-active' : '';
                const resultClass = trade.netR >= 0 ? 'win' : 'loss';
                return `
                    <button class="backtest-trade-row${active}" type="button" data-trade-id="${escapeAttr(trade.id)}">
                        <span class="trade-side ${trade.direction}">${escapeHtml(displayTradeSide(trade.direction))}</span>
                        <span class="trade-row-main">
                            <strong>${escapeHtml(displayExitType(trade.exitType))} | ${escapeHtml(formatChartTime(trade.exitTime))}</strong>
                            <span>${escapeHtml(formatPrice(trade.entryPrice, trade.symbol))} → ${escapeHtml(formatPrice(trade.exitPrice, trade.symbol))}</span>
                        </span>
                        <span class="trade-r ${resultClass}">${escapeHtml(formatSigned(trade.netR, 2))}R</span>
                    </button>
                `;
            })
            .join('');
        $$('[data-trade-id]').forEach((button) => {
            button.addEventListener('click', () => {
                selectTrade(button.dataset.tradeId, true);
            });
        });
    }
    function renderChartBacktestSidebar(run) {
        const count = $('#chartSideTradeCount');
        const statsTarget = $('#chartSideBacktestStats');
        const diagnosticsTarget = $('#chartSideDiagnostics');
        const list = $('#chartSideTradeList');
        if (!count || !statsTarget || !diagnosticsTarget || !list) return;
        if (state.activeTab === 'pa-training') {
            count.textContent = '0';
            statsTarget.innerHTML = '';
            diagnosticsTarget.innerHTML = '';
            list.innerHTML = '';
            return;
        }
        const trades = run ? filteredTrades(run.trades) : [];
        count.textContent = number(trades.length);
        if (!run) {
            statsTarget.innerHTML = '<div class="side-empty">Chạy backtest để xem lệnh trực tiếp trên chart.</div>';
            diagnosticsTarget.innerHTML = '';
            list.innerHTML = '';
            return;
        }
        const stats = run.stats || {};
        statsTarget.innerHTML = `
            <div class="chart-side-stat"><span>Số lệnh</span><strong>${number(stats.trades || trades.length)}</strong></div>
            <div class="chart-side-stat"><span>Winrate</span><strong>${formatFixed(stats.winRate || 0, 1)}%</strong></div>
            <div class="chart-side-stat"><span>Lãi/lỗ R</span><strong>${formatSigned(stats.totalR || 0, 2)}</strong></div>
            <div class="chart-side-stat"><span>PF</span><strong>${Number.isFinite(stats.profitFactor) ? formatFixed(stats.profitFactor, 2) : '∞'}</strong></div>
        `;
        diagnosticsTarget.innerHTML = chartDiagnosticsHtml(run);
        if (!trades.length) {
            list.innerHTML = '<div class="side-empty">Không có lệnh khớp. Xem mục lý do lọc ở trên để biết vì sao ít lệnh.</div>';
            return;
        }
        list.innerHTML = trades
            .slice()
            .reverse()
            .map((trade) => {
                const active = trade.id === state.chartView.selectedTradeId ? ' is-active' : '';
                const resultClass = trade.netR >= 0 ? 'win' : 'loss';
                return `
                    <button class="chart-side-trade${active}" type="button" data-chart-side-trade-id="${escapeAttr(trade.id)}">
                        <span class="trade-side ${trade.direction}">${escapeHtml(displayTradeSide(trade.direction))}</span>
                        <span>
                            <strong>${escapeHtml(formatChartTime(trade.entryTime))}</strong>
                            <small>${escapeHtml(formatPrice(trade.entryPrice, trade.symbol))} → ${escapeHtml(formatPrice(trade.exitPrice, trade.symbol))}</small>
                        </span>
                        <b class="trade-r ${resultClass}">${escapeHtml(formatSigned(trade.netR, 2))}R</b>
                    </button>
                `;
            })
            .join('');
        $$('[data-chart-side-trade-id]').forEach((button) => {
            button.addEventListener('click', () => selectTrade(button.dataset.chartSideTradeId, true));
        });
    }

    function chartDiagnosticsHtml(run) {
        const diagnostics = run?.summary?.diagnostics || run?.debug?.diagnostics || {};
        const skippedReasons = diagnostics.skippedReasons || {};
        const topReasons = Object.entries(skippedReasons)
            .filter(([, value]) => Number(value) > 0)
            .sort((a, b) => Number(b[1]) - Number(a[1]))
            .slice(0, 4);
        const rows = [
            ['M15', diagnostics.totalM15Candles],
            ['H4', diagnostics.totalH4Candles],
            ['Setup xét', diagnostics.setupsConsidered],
            ['Vào vùng H4', diagnostics.priceReturnsToH4Zone],
            ['Lệnh chờ', diagnostics.pendingLimitCreated],
            ['Lệnh khớp', diagnostics.pendingLimitFilled],
            ['Bị loại', diagnostics.setupsRejected],
        ].filter(([, value]) => value !== undefined && value !== null);
        const reasonHtml = topReasons.length
            ? topReasons.map(([reason, value]) => `<div class="chart-reject-row"><span>${escapeHtml(translateDisplayText(reason))}</span><strong>${number(value)}</strong></div>`).join('')
            : '<div class="side-empty">Không có lý do loại nổi bật.</div>';
        return `
            <div class="chart-diagnostics-title">Vì sao ít lệnh</div>
            <div class="chart-diagnostics-grid">
                ${rows.map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${number(value || 0)}</strong></div>`).join('')}
            </div>
            <div class="chart-reject-list">${reasonHtml}</div>
        `;
    }

    function renderChartControlState() {
        $$('[data-chart-toggle]').forEach((input) => {
            const key = input.dataset.chartToggle;
            if (key in state.chartView.indicators) input.checked = !!state.chartView.indicators[key];
            if (key in state.chartView.overlays) input.checked = !!state.chartView.overlays[key];
        });
        const chartType = $('#chartTypeSelect');
        if (chartType) chartType.value = state.chartView.chartType || 'candles';
        const magnet = $('#chartMagnetMode');
        if (magnet) magnet.value = state.chartView.magnetMode || 'off';
        const stay = $('#chartStayDrawingToggle');
        if (stay) stay.checked = !!state.chartView.stayInDrawingMode;
        const lock = $('#chartLockToggle');
        if (lock) lock.checked = !!state.chartView.drawingsLocked;
        const hide = $('#chartHideDrawingsToggle');
        if (hide) hide.checked = !state.chartView.drawingsVisible;
        const favorites = $('#chartFavoritesToggle');
        if (favorites) favorites.checked = !!state.chartView.favoritesVisible;
        const indicatorTemplate = $('#chartIndicatorTemplate');
        if (indicatorTemplate) indicatorTemplate.value = state.chartView.indicatorTemplate || 'default';
        const timezone = $('#chartTimezoneSelect');
        if (timezone) timezone.value = state.chartView.timezone || 'UTC';
        const scaleMode = $('#chartScaleModeSelect');
        if (scaleMode) scaleMode.value = state.chartView.scaleMode || 'auto';
        const layoutBtn = $('#chartMultiLayoutBtn');
        if (layoutBtn) layoutBtn.textContent = `${state.chartView.layoutSlots || 1} biểu đồ`;
        renderLayoutSelect();
        const runSelect = $('#chartRunSelect');
        if (runSelect) {
            const runs = state.activeMarketData ? getChartAnalytics(state.activeMarketData).runs : BACKTEST_RUNS;
            runSelect.innerHTML = runs.map((run) => `<option value="${escapeAttr(run.id)}">${escapeHtml(run.name)}</option>`).join('');
            if (!runs.some((run) => run.id === state.chartView.activeRunId)) {
                state.chartView.activeRunId = runs[0]?.id || 'standard';
            }
            runSelect.value = state.chartView.activeRunId;
        }
        const filter = $('#chartTradeFilter');
        if (filter) filter.value = state.chartView.tradeFilter;
        const speed = $('#chartReplaySpeed');
        if (speed) speed.value = String(state.chartView.replay.speed || 1);
        const theme = $('#chartThemeBtn');
        const history = state.activeMarketData ? chartHistoryFor() : { undo: [], redo: [] };
        const undo = $('#chartUndoBtn');
        if (undo) undo.disabled = !history.undo.length;
        const redo = $('#chartRedoBtn');
        if (redo) redo.disabled = !history.redo.length;
        renderChartDrawingList();
        renderFavoriteToolbar();
        updateChartBottomStatus();
        setChartButtonLabel(theme, bodyIsDark() ? 'Nền sáng' : 'Nền tối');
    }

    async function saveChartDrawingAsPaZone(id) {
        if (!state.activeMarketData) return;
        const drawing = getChartDrawings(state.activeMarketData).find((item) => getDrawingId(item) === id);
        if (!drawing || drawing.type !== 'rect') {
            showToast('Chỉ đưa được vùng chữ nhật canh buy/canh sell vào Dạy PA.');
            return;
        }
        if (String(state.activeMarketData.timeframe || '').toUpperCase() !== 'H4') {
            showToast('Vùng dạy PA nên vẽ trên chart H4. Chuyển sang H4 rồi bấm Dạy PA.');
            return;
        }
        const prices = (drawing.points || []).map((point) => Number(point.price)).filter(Number.isFinite);
        if (prices.length < 2) {
            showToast('Vùng vẽ chưa có đủ giá trên/dưới.');
            return;
        }
        const low = Math.min(...prices);
        const high = Math.max(...prices);
        const type = drawing.zoneType === 'supply' ? 'supply' : 'demand';
        const note = drawing.label || `Vùng canh ${type === 'supply' ? 'SELL' : 'BUY'} do người dùng vẽ trên chart H4.`;
        const data = await api('pa_training_manual_zone_save', {
            symbol: state.activeMarketData.symbol,
            timeframe: 'H4',
            type,
            low,
            high,
            note,
        });
        const zone = data.manual_zone;
        state.paTrainingSymbol = zone.symbol;
        state.paTrainingTimeframe = 'H4';
        state.paTrainingZoneFilter = 'manual';
        state.paTrainingMode = 'cases';
        state.paTrainingZones = [zone, ...(state.paTrainingZones || []).filter((item) => item.id !== zone.id)];
        state.paTrainingSelectedZoneId = zone.id;
        if (data.pa_training_memory) state.paTrainingMemory = normalizePaTrainingMemory(data.pa_training_memory);
        await loadPaTrainingChartForSelection('H4', { silent: true });
        activateTab('pa-training');
        renderPaTraining();
        showToast('Đã đưa vùng vẽ trên chart vào Dạy Price Action.');
    }
    function renderChartDrawingList() {
        const list = $('#chartDrawingList');
        const count = $('#drawingListCount');
        if (!list || !count) return;
        const drawings = state.activeMarketData ? getChartDrawings(state.activeMarketData) : [];
        count.textContent = number(drawings.length);
        if (!drawings.length) {
            list.innerHTML = '<div class="review-item"><span>Chưa có hình vẽ nào.</span></div>';
            return;
        }
        const selectedIds = new Set(state.chartView.selectedDrawingIds || []);
        list.innerHTML = drawings
            .slice()
            .reverse()
            .map((drawing) => {
                const id = getDrawingId(drawing);
                const active = selectedIds.has(id) ? ' is-active' : '';
                const hidden = drawing.hidden ? ' is-hidden' : '';
                return `
                    <div class="drawing-row${active}${hidden}" role="button" tabindex="0" data-drawing-id="${escapeAttr(id)}">
                        <span class="drawing-row-copy">
                            <strong>${escapeHtml(drawingDisplayName(drawing))}</strong>
                            <span>${escapeHtml(drawingMetaText(drawing))}</span>
                        </span>
                        <span class="drawing-row-actions">
                                                        <button type="button" data-drawing-action="visibility" data-drawing-id="${escapeAttr(id)}">${drawing.hidden ? 'Hiện' : 'Ẩn'}</button>
                            ${drawing.type === 'rect' ? `<button type="button" data-drawing-action="teach-pa" data-drawing-id="${escapeAttr(id)}">Dạy PA</button>` : ''}
                            <button type="button" data-drawing-action="delete" data-drawing-id="${escapeAttr(id)}">Xóa</button>
                        </span>
                    </div>
                `;
            })
            .join('');
        $$('[data-drawing-id]').forEach((element) => {
            if (element.dataset.boundDrawing === '1') return;
            element.dataset.boundDrawing = '1';
            element.addEventListener('click', (event) => {
                const action = event.target?.dataset?.drawingAction;
                const id = element.dataset.drawingId || event.target?.dataset?.drawingId;
                if (!id) return;
                event.preventDefault();
                event.stopPropagation();
                if (action === 'visibility') {
                    toggleDrawingHidden(id);
                } else if (action === 'lock') {
                    toggleDrawingLocked(id);
                } else if (action === 'duplicate') {
                    duplicateSelectedDrawings([id]);
                } else if (action === 'rename') {
                    renameDrawing(id);
                } else if (action === 'group') {
                    groupSelectedDrawings(id);
                } else if (action === 'teach-pa') {
                    saveChartDrawingAsPaZone(id).catch((e) => showToast(e.message));
                } else if (action === 'delete') {
                    state.chartView.selectedDrawingIds = [id];
                    if (deleteSelectedDrawings() && state.activeMarketData) drawMarketChart(state.activeMarketData);
                } else {
                    if (event.ctrlKey || event.metaKey || event.shiftKey) {
                        const selected = new Set(state.chartView.selectedDrawingIds || []);
                        if (selected.has(id)) selected.delete(id);
                        else selected.add(id);
                        state.chartView.selectedDrawingIds = Array.from(selected);
                    } else {
                        state.chartView.selectedDrawingIds = [id];
                    }
                    if (state.activeMarketData) drawMarketChart(state.activeMarketData);
                }
            });
        });
    }

    function bindFloatingToolbarEvents() {
        bindFloatingToolbarDrag();
        $('#drawingColorInput')?.addEventListener('input', (event) => updateSelectedDrawingStyle({ color: event.target.value }));
        $('#drawingWidthSelect')?.addEventListener('change', (event) => updateSelectedDrawingStyle({ width: Number(event.target.value) || 2 }));
        $('#drawingLineStyleSelect')?.addEventListener('change', (event) => updateSelectedDrawingStyle({ lineStyle: event.target.value || 'solid' }));
        $('#drawingOpacityInput')?.addEventListener('input', (event) => updateSelectedDrawingStyle({ opacity: Number(event.target.value) || 0 }));
        $('#drawingTextSizeSelect')?.addEventListener('change', (event) => updateSelectedDrawingStyle({ textSize: Number(event.target.value) || 12 }));
        $('#drawingLockBtn')?.addEventListener('click', () => {
            toggleSelectedDrawingLock();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#drawingHideBtn')?.addEventListener('click', () => {
            toggleSelectedDrawingHidden();
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#drawingSavePaZoneBtn')?.addEventListener('click', () => {
            const drawing = selectedDrawingObjects()[0];
            const id = drawing ? getDrawingId(drawing) : '';
            if (!id) return;
            saveChartDrawingAsPaZone(id).catch((e) => showToast(e.message));
        });
        $('#drawingJumpH4Btn')?.addEventListener('click', () => jumpSelectedDrawingToTimeframe('H4').catch((e) => showToast(e.message)));
        $('#drawingJumpH1Btn')?.addEventListener('click', () => jumpSelectedDrawingToTimeframe('H1').catch((e) => showToast(e.message)));
        $('#drawingJumpM15Btn')?.addEventListener('click', () => jumpSelectedDrawingToTimeframe('M15').catch((e) => showToast(e.message)));
        $('#drawingCloneBtn')?.addEventListener('click', () => duplicateSelectedDrawings());
        $('#drawingDeleteBtn')?.addEventListener('click', () => {
            if (deleteSelectedDrawings() && state.activeMarketData) drawMarketChart(state.activeMarketData);
        });
        $('#drawingPropertiesBtn')?.addEventListener('click', editSelectedDrawingText);
        $('#drawingSaveStyleBtn')?.addEventListener('click', saveSelectedDrawingStyle);
    }

    function bindFloatingToolbarDrag() {
        const toolbar = $('#chartFloatingToolbar');
        const handle = $('#drawingToolbarDragHandle');
        if (!toolbar || !handle || handle.dataset.boundDrag === '1') return;
        handle.dataset.boundDrag = '1';
        handle.addEventListener('mousedown', (event) => {
            if (event.button !== 0) return;
            const wrap = toolbar.parentElement;
            if (!wrap) return;
            event.preventDefault();
            event.stopPropagation();
            const toolbarRect = toolbar.getBoundingClientRect();
            const wrapRect = wrap.getBoundingClientRect();
            state.chartView.floatingToolbarDrag = {
                startX: event.clientX,
                startY: event.clientY,
                left: toolbarRect.left - wrapRect.left,
                top: toolbarRect.top - wrapRect.top,
            };
            toolbar.classList.add('is-dragging');
        });
        window.addEventListener('mousemove', (event) => {
            const drag = state.chartView.floatingToolbarDrag;
            if (!drag) return;
            const wrap = toolbar.parentElement;
            if (!wrap) return;
            const left = clamp(drag.left + event.clientX - drag.startX, 8, Math.max(8, wrap.clientWidth - toolbar.offsetWidth - 8));
            const top = clamp(drag.top + event.clientY - drag.startY, 8, Math.max(8, wrap.clientHeight - toolbar.offsetHeight - 8));
            state.chartView.floatingToolbarPosition = { left, top };
            toolbar.style.left = `${left}px`;
            toolbar.style.top = `${top}px`;
        });
        window.addEventListener('mouseup', () => {
            if (!state.chartView.floatingToolbarDrag) return;
            state.chartView.floatingToolbarDrag = null;
            toolbar.classList.remove('is-dragging');
            persistChartSettings();
        });
    }

    function renderFloatingToolbar() {
        const toolbar = $('#chartFloatingToolbar');
        if (!toolbar || !state.activeMarketData || !state.chartView.lastLayout || !state.chartView.drawingsVisible) return;
        const drawing = selectedDrawingObjects()[0];
        if (!drawing || drawing.hidden) {
            toolbar.hidden = true;
            return;
        }
        const bounds = drawingScreenBounds(drawing, state.chartView.lastLayout);
        if (!bounds) {
            toolbar.hidden = true;
            return;
        }
        const wrap = toolbar.parentElement;
        toolbar.hidden = false;
        const manualPosition = state.chartView.floatingToolbarPosition;
        const hasManualPosition = manualPosition
            && Number.isFinite(Number(manualPosition.left))
            && Number.isFinite(Number(manualPosition.top));
        const autoLeft = bounds.left;
        const autoTop = bounds.top - toolbar.offsetHeight - 10;
        const left = clamp(hasManualPosition ? Number(manualPosition.left) : autoLeft, 8, Math.max(8, wrap.clientWidth - toolbar.offsetWidth - 8));
        const top = clamp(hasManualPosition ? Number(manualPosition.top) : autoTop, 8, Math.max(8, wrap.clientHeight - toolbar.offsetHeight - 8));
        if (hasManualPosition) state.chartView.floatingToolbarPosition = { left, top };
        toolbar.style.left = `${left}px`;
        toolbar.style.top = `${top}px`;
        $('#drawingColorInput').value = drawing.color || state.chartView.drawingStyle.color || '#1f6feb';
        $('#drawingWidthSelect').value = String(drawing.width || 2);
        $('#drawingLineStyleSelect').value = drawing.lineStyle || 'solid';
        $('#drawingOpacityInput').value = String(Number(drawing.opacity ?? 16));
        $('#drawingTextSizeSelect').value = String(Number(drawing.textSize || 12));
        $('#drawingLockBtn').textContent = drawing.locked ? 'Mở khóa' : 'Khóa';
        $('#drawingHideBtn').textContent = drawing.hidden ? 'Hiện' : 'Ẩn';
        const paZoneButton = $('#drawingSavePaZoneBtn');
        if (paZoneButton) {
            const canSavePaZone = state.activeTab === 'pa-training'
                && drawing.type === 'rect'
                && ['supply', 'demand'].includes(String(drawing.zoneType || ''));
            paZoneButton.hidden = !canSavePaZone;
            const paZoneTitle = drawing.zoneType === 'supply'
                ? 'Thêm vùng canh sell AI chưa bắt được vào Dạy PA'
                : 'Thêm vùng canh buy AI chưa bắt được vào Dạy PA';
            paZoneButton.textContent = '+ Vùng';
            paZoneButton.title = paZoneTitle;
            paZoneButton.setAttribute('aria-label', paZoneTitle);
        }
        const focusRange = drawingTimeValues(drawing);
        [['drawingJumpH4Btn', 'H4'], ['drawingJumpH1Btn', 'H1'], ['drawingJumpM15Btn', 'M15']].forEach(([id, timeframe]) => {
            const button = document.getElementById(id);
            if (!button) return;
            const isCurrent = String(state.activeMarketData?.timeframe || '').toUpperCase() === timeframe;
            button.hidden = state.activeTab !== 'pa-training' || !focusRange.length || isCurrent;
            button.title = `Mở đúng vùng này trên ${timeframe}`;
            button.setAttribute('aria-label', `Mở đúng vùng này trên ${timeframe}`);
        });
        const supportsText = TEXT_TOOLS.has(drawing.type) || drawing.type === 'price-label';
        $('#drawingTextSizeSelect').hidden = !supportsText;
        $('#drawingPropertiesBtn').hidden = !supportsText;
    }

    function drawingTimeValues(drawing, data = state.activeMarketData) {
        const values = [];
        const addTime = (value) => {
            const time = timeToSeconds(value);
            if (Number.isFinite(time)) values.push(time);
        };
        const addPoint = (point) => {
            if (!point) return;
            const raw = point.time ?? point.time_iso;
            addTime(raw);
            if (!Number.isFinite(timeToSeconds(raw)) && data?.bars?.length && Number.isFinite(Number(point.barIndex))) {
                const index = clamp(Math.round(Number(point.barIndex)), 0, data.bars.length - 1);
                const bar = data.bars[index];
                addTime(bar?.time_iso || bar?.time);
            }
        };
        (drawing.points || []).forEach(addPoint);
        addPoint(drawing.point);
        addTime(drawing.time ?? drawing.time_iso);
        addTime(drawing.startTime ?? drawing.startTimeIso);
        addTime(drawing.endTime ?? drawing.endTimeIso);
        if (!values.length && data?.bars?.length) {
            ['barIndex', 'startBar', 'endBar'].forEach((key) => {
                if (!Number.isFinite(Number(drawing[key]))) return;
                const bar = data.bars[clamp(Math.round(Number(drawing[key])), 0, data.bars.length - 1)];
                addTime(bar?.time_iso || bar?.time);
            });
        }
        return [...new Set(values)].sort((a, b) => a - b);
    }

    function focusChartTimeRange(times) {
        const bars = state.activeMarketData?.bars || [];
        const cleanTimes = (times || []).filter(Number.isFinite);
        if (!bars.length || !cleanTimes.length) return false;
        const indices = cleanTimes.map((time) => chartIndexForBarsTime(bars, time, 0));
        const left = Math.min(...indices);
        const right = Math.max(...indices);
        const center = (left + right) / 2;
        const minSpan = Math.min(12, bars.length);
        const span = clamp(Math.max(48, Math.round((right - left + 1) * 3.2)), minSpan, bars.length);
        const first = clamp(Math.round(center - span / 2), 0, Math.max(0, bars.length - span));
        const offsetLimits = chartRightOffsetLimits(bars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(bars.length - first - span, offsetLimits.min, offsetLimits.max);
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        drawMarketChart(state.activeMarketData);
        if (state.activeTab === 'pa-training') persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
        return true;
    }

    async function jumpSelectedDrawingToTimeframe(timeframe) {
        const drawing = selectedDrawingObjects()[0];
        if (!drawing) {
            showToast('Chọn vùng hoặc hình vẽ trước khi nhảy khung.');
            return;
        }
        const id = getDrawingId(drawing);
        const times = drawingTimeValues(drawing);
        if (!times.length) {
            showToast('Hình vẽ này chưa có mốc thời gian để căn sang khung khác. Vẽ lại vùng mới rồi thử tiếp.');
            return;
        }
        const symbol = state.activeMarketData?.symbol || state.paTrainingSymbol || chartSymbols()[0];
        const targetTimeframe = String(timeframe || 'M15').toUpperCase();
        if (state.activeTab === 'pa-training') {
            await loadPaTrainingChartForSymbol(symbol, targetTimeframe, { silent: true });
        } else {
            await changeChartTimeframe(targetTimeframe);
        }
        const stillThere = findDrawingById(id);
        state.chartView.selectedDrawingIds = stillThere ? [id] : [];
        focusChartTimeRange(times);
        updateChartTimeframeButtons();
        if (state.activeTab === 'pa-training') renderPaTrainingChat();
    }
    function updateSelectedDrawingStyle(patch) {
        const drawings = selectedDrawingObjects().filter((drawing) => !drawing.locked);
        if (!drawings.length) return;
        pushDrawingHistory();
        drawings.forEach((drawing) => Object.assign(drawing, patch));
        persistChartDrawings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function saveSelectedDrawingStyle() {
        const drawing = selectedDrawingObjects()[0];
        if (!drawing) return;
        state.chartView.drawingStyle = {
            color: drawing.color || '#1f6feb',
            width: Number(drawing.width || 2),
            lineStyle: drawing.lineStyle || 'solid',
            opacity: Number(drawing.opacity ?? 16),
            textSize: Number(drawing.textSize || 12),
        };
        persistChartSettings();
        showToast('Đã lưu kiểu mặc định cho công cụ');
    }

    function renderFavoriteToolbar() {
        const toolbar = $('#chartFavoriteToolbar');
        if (!toolbar) return;
        if (!state.chartView.favoritesVisible) {
            toolbar.hidden = true;
            return;
        }
        toolbar.hidden = false;
        toolbar.innerHTML = (state.chartView.favoriteTools || [])
            .map((tool) => `<button type="button" class="favorite-tool ${state.chartView.activeTool === tool ? 'is-active' : ''}" data-favorite-tool="${escapeAttr(tool)}">${escapeHtml(shortToolLabel(tool))}</button>`)
            .join('');
        $$('[data-favorite-tool]').forEach((button) => {
            button.addEventListener('click', () => setChartTool(button.dataset.favoriteTool));
        });
    }

    function shortToolLabel(tool) {
        const map = {
            cursor: '+',
            select: '□',
            trendline: '╱',
            ray: '↗',
            hline: '─',
            fib: 'F',
            measure: '↕',
            'long-position': 'L',
            'short-position': 'S',
            text: 'T',
        };
        return map[tool] || (DRAWING_TOOL_LABELS[tool] || tool).slice(0, 3);
    }

    function renderChartSidePanels(layout = state.chartView.lastLayout) {
        renderAlertList();
        renderDataWindow(layout);
        renderNewsList();
    }

    function renderAlertList() {
        const target = $('#chartAlertList');
        const count = $('#chartAlertCount');
        if (!target || !count) return;
        const alerts = state.activeMarketData ? getChartDrawings(state.activeMarketData).filter((drawing) => drawing.type === 'alert') : [];
        count.textContent = number(alerts.length);
        target.innerHTML = alerts.length
            ? alerts.map((alert) => `<button type="button" class="side-row" data-alert-id="${escapeAttr(getDrawingId(alert))}">${escapeHtml(alert.label || 'Cảnh báo')}<span>${escapeHtml(formatPrice(alert.price, state.activeMarketData?.symbol))}</span></button>`).join('')
            : '<div class="side-empty">Chưa có cảnh báo.</div>';
    }

    function renderDataWindow(layout) {
        const target = $('#chartDataWindow');
        if (!target || !layout?.data) return;
        const point = state.chartView.crosshairPoint;
        const index = point
            ? Math.round(layout.barIndexAtX(point.x))
            : Math.min(layout.allBars.length - 1, layout.barsLastExclusive - 1);
        const bar = layout.allBars[index];
        if (!bar) {
            target.innerHTML = '<div class="side-empty">Chưa có dữ liệu.</div>';
            return;
        }
        target.innerHTML = `
            <div class="data-window-row"><span>Thời gian</span><strong>${escapeHtml(formatChartTime(bar.time_iso || bar.time))}</strong></div>
            <div class="data-window-row"><span>Mở cửa</span><strong>${escapeHtml(formatPrice(bar.open, layout.data.symbol))}</strong></div>
            <div class="data-window-row"><span>Cao nhất</span><strong>${escapeHtml(formatPrice(bar.high, layout.data.symbol))}</strong></div>
            <div class="data-window-row"><span>Thấp nhất</span><strong>${escapeHtml(formatPrice(bar.low, layout.data.symbol))}</strong></div>
            <div class="data-window-row"><span>Đóng cửa</span><strong>${escapeHtml(formatPrice(bar.close, layout.data.symbol))}</strong></div>
            <div class="data-window-row"><span>Khối lượng</span><strong>${escapeHtml(number(bar.tick_volume || bar.real_volume || 0))}</strong></div>
        `;
    }

    function renderNewsList() {
        const target = $('#chartNewsList');
        if (!target) return;
        const symbol = state.activeMarketData?.symbol || '';
        const currencies = symbol.length >= 6 ? [symbol.slice(0, 3), symbol.slice(3, 6)] : [];
        target.innerHTML = currencies.length
            ? currencies.map((currency) => `<div class="side-row"><strong>${escapeHtml(currency)}</strong><span>Tin đỏ: kiểm tra lịch ngoài trước khi vào lệnh.</span></div>`).join('')
            : '<div class="side-empty">Chưa chọn cặp tiền.</div>';
    }

    function applyIndicatorTemplate(template) {
        state.chartView.indicatorTemplate = template || 'default';
        const presets = {
            clean: { ema20: false, ema50: false, ema200: false, sma100: false, bollinger: false, vwap: false, atrStop: false, rsi: false, macd: false },
            trend: { ema20: true, ema50: true, ema200: true, sma100: true, bollinger: false, vwap: true, atrStop: true, rsi: false, macd: false },
            momentum: { ema20: true, ema50: true, ema200: false, sma100: false, bollinger: true, vwap: false, atrStop: false, rsi: true, macd: true },
            default: { ema20: true, ema50: true, ema200: false, sma100: false, bollinger: false, vwap: false, atrStop: false, rsi: false, macd: false },
        };
        Object.assign(state.chartView.indicators, presets[state.chartView.indicatorTemplate] || presets.default);
        persistChartSettings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function saveCurrentIndicatorTemplate() {
        state.chartView.indicatorTemplate = 'default';
        persistChartSettings();
        showToast('Đã lưu trạng thái chỉ báo hiện tại');
    }

    function createAlertAtLastPrice() {
        const bars = state.activeMarketData?.bars || [];
        const last = bars[bars.length - 1];
        if (!last) return;
        commitDrawing(styledDrawing({
            type: 'alert',
            price: Number(last.close),
            label: `Cảnh báo ${formatPrice(last.close, state.activeMarketData.symbol)}`,
            color: '#bc3d3d',
        }));
    }

    function renderLayoutSelect() {
        const select = $('#chartLayoutSelect');
        if (!select) return;
        const layouts = loadChartLayouts();
        const names = ['Default', ...Object.keys(layouts).filter((name) => name !== 'Default')];
        select.innerHTML = names.map((name) => `<option value="${escapeAttr(name)}">${escapeHtml(name === 'Default' ? 'Mặc định' : name)}</option>`).join('');
        select.value = state.chartView.activeLayoutName || 'Default';
    }

    function chartLayoutSnapshot() {
        return {
            chartType: state.chartView.chartType,
            timeframe: state.chartView.timeframe,
            indicators: state.chartView.indicators,
            teachingLevelMarkersVisible: state.chartView.teachingLevelMarkersVisible !== false,
            overlays: state.chartView.overlays,
            barsOnScreen: state.chartView.barsOnScreen,
            rightOffset: state.chartView.rightOffset,
            priceScale: state.chartView.priceScale,
            priceOffset: state.chartView.priceOffset,
            scaleMode: state.chartView.scaleMode,
            timezone: state.chartView.timezone,
            compareSymbols: state.chartView.compareSymbols,
            layoutSlots: state.chartView.layoutSlots,
            drawings: state.activeMarketData ? getChartDrawings(state.activeMarketData) : [],
        };
    }

    function saveChartLayout() {
        const name = window.prompt('Tên bố cục', state.chartView.activeLayoutName === 'Default' ? '' : state.chartView.activeLayoutName);
        if (!name) return;
        const layouts = loadChartLayouts();
        layouts[name.trim()] = chartLayoutSnapshot();
        persistChartLayouts(layouts);
        state.chartView.activeLayoutName = name.trim();
        renderChartControlState();
        showToast('Đã lưu bố cục biểu đồ');
    }

    function loadSelectedChartLayout() {
        const name = $('#chartLayoutSelect')?.value || 'Default';
        const layouts = loadChartLayouts();
        const layout = layouts[name];
        if (!layout) {
            showToast('Bố cục mặc định đang dùng trạng thái hiện tại');
            return;
        }
        state.chartView.activeLayoutName = name;
        Object.assign(state.chartView.indicators, layout.indicators || {});
        Object.assign(state.chartView.overlays, layout.overlays || {});
        ['chartType', 'barsOnScreen', 'rightOffset', 'priceScale', 'priceOffset', 'scaleMode', 'timezone', 'layoutSlots'].forEach((key) => {
            if (layout[key] !== undefined) state.chartView[key] = layout[key];
        });
        if (Array.isArray(layout.compareSymbols)) state.chartView.compareSymbols = layout.compareSymbols;
        if (Array.isArray(layout.drawings) && state.activeMarketData) {
            state.chartView.drawings[chartDrawingKey(state.activeMarketData)] = layout.drawings;
            persistChartDrawings();
        }
        persistChartSettings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function cycleChartLayoutSlots() {
        state.chartView.layoutSlots = state.chartView.layoutSlots >= 4 ? 1 : state.chartView.layoutSlots + 1;
        persistChartSettings();
        renderChartControlState();
        showToast(`Đã chuyển sang bố cục ${state.chartView.layoutSlots} biểu đồ`);
    }

    function quickSearchChart() {
        const query = String($('#chartQuickSearchInput')?.value || '').trim();
        if (!query) return;
        const upper = query.toUpperCase();
        if ((state.strategy.symbols || []).includes(upper)) {
            openWatchSymbol(upper).catch((e) => showToast(e.message));
            return;
        }
        const date = new Date(query);
        if (!Number.isNaN(date.getTime())) {
            focusChartTime(Math.floor(date.getTime() / 1000));
            return;
        }
        showToast('Không tìm thấy cặp tiền hoặc ngày');
    }

    function openChartSettings() {
        const bars = window.prompt('Số nến hiển thị', String(state.chartView.barsOnScreen || 180));
        if (bars !== null && Number.isFinite(Number(bars))) {
            const total = state.activeMarketData?.bars?.length || 1000;
            state.chartView.barsOnScreen = clamp(Math.round(Number(bars)), Math.min(12, total), total);
        }
        const rr = window.prompt('RR mặc định cho vị thế mua/bán', String(defaultPositionRr()));
        if (rr !== null && Number.isFinite(Number(rr)) && state.strategy?.risk_reward) {
            state.strategy.risk_reward.default_rr = Math.max(0.1, Number(rr));
        }
        persistChartSettings();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function applyRangePreset(preset) {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        if (preset === 'ALL') {
            fitFullChart();
            return;
        }
        const minutes = MT5_TIMEFRAME_MINUTES[state.activeMarketData.timeframe] || 15;
        const days = {
            '1D': 1,
            '5D': 5,
            '1M': 30,
            '3M': 90,
            '6M': 180,
            YTD: Math.max(1, (Date.now() - Date.UTC(new Date().getUTCFullYear(), 0, 1)) / 86400000),
            '1Y': 365,
            '5Y': 365 * 5,
        }[preset] || 30;
        state.chartView.barsOnScreen = clamp(Math.round(days * 1440 / minutes), Math.min(12, bars.length), bars.length);
        state.chartView.rightOffset = 0;
        drawMarketChart(state.activeMarketData);
    }

    function goToChartDate() {
        const value = $('#chartGoToDate')?.value;
        if (!value) return;
        const date = new Date(`${value}T00:00:00Z`);
        if (!Number.isNaN(date.getTime())) focusChartTime(Math.floor(date.getTime() / 1000));
    }

    function focusChartTime(timestamp) {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        let bestIndex = 0;
        let bestDistance = Infinity;
        bars.forEach((bar, index) => {
            const time = barTimeValue(bar);
            const distance = Math.abs(time - timestamp);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestIndex = index;
            }
        });
        centerChartOnIndex(bestIndex);
        drawMarketChart(state.activeMarketData);
    }

    function resetChartPriceScaleForMode() {
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
    }

    function togglePaperTradePanel() {
        const panel = $('#chartTradingPanel');
        if (panel) panel.hidden = !panel.hidden;
    }

    function placePaperTrade() {
        const bars = state.activeMarketData?.bars || [];
        const last = bars[bars.length - 1];
        if (!last) return;
        const side = $('#paperTradeSide')?.value || 'long';
        const size = Number($('#paperTradeSize')?.value || 1);
        const line = `${formatChartTime(last.time_iso || last.time)} ${displayTradeSide(side)} ${formatNumberValue(size)} @ ${formatPrice(last.close, state.activeMarketData.symbol)}`;
        const log = $('#paperTradeLog');
        if (log) log.innerHTML = `<div>${escapeHtml(line)}</div>${log.innerHTML}`;
        commitDrawing(createPositionDrawing(side, { barIndex: bars.length - 1, price: Number(last.close) }, state.chartView.lastLayout));
    }

    function updateChartBottomStatus() {
        const target = $('#chartBottomStatus');
        if (!target) return;
        const magnet = state.chartView.magnetMode === 'off' ? 'Tắt nam châm' : state.chartView.magnetMode === 'weak' ? 'Nam châm yếu' : 'Nam châm mạnh';
        target.textContent = `${DRAWING_TOOL_LABELS[state.chartView.activeTool] || state.chartView.activeTool} | ${magnet}${state.chartView.stayInDrawingMode ? ' | Vẽ liên tục' : ''}`;
    }

    function applyZoomBox(box, layout) {
        if (!box || !layout) return;
        const a = screenPointFromDomain(box.start, layout);
        const b = screenPointFromDomain(box.end, layout);
        const leftIndex = Math.floor(Math.min(box.start.barIndex, box.end.barIndex));
        const rightIndex = Math.ceil(Math.max(box.start.barIndex, box.end.barIndex));
        const span = clamp(rightIndex - leftIndex + 1, Math.min(12, layout.allBars.length), layout.allBars.length);
        const moved = Math.abs(a.x - b.x) + Math.abs(a.y - b.y);
        if (moved < 20) return;
        const offsetLimits = chartRightOffsetLimits(layout.allBars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(layout.allBars.length - leftIndex - span, offsetLimits.min, offsetLimits.max);
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        drawMarketChart(state.activeMarketData);
    }

    function drawingDisplayName(drawing) {
        if (!drawing) return 'Hình vẽ';
        if (drawing.name) return drawing.name;
        if (drawing.type === 'rect' && drawing.zoneType === 'supply') return 'Vùng canh sell';
        if (drawing.type === 'rect' && drawing.zoneType === 'demand') return 'Vùng canh buy';
        if (drawing.type === 'position') return drawing.direction === 'long' ? 'Vị thế mua' : 'Vị thế bán';
        if (drawing.type === 'text') return drawing.text || 'Ghi chú';
        if (drawing.type === 'alert') return drawing.label || 'Cảnh báo giá';
        return DRAWING_TOOL_LABELS[drawing.type] || drawing.type || 'Hình vẽ';
    }

    function drawingMetaText(drawing) {
        const flags = [
            drawing.group ? `Nhóm: ${drawing.group}` : '',
            drawing.hidden ? 'ẩn' : '',
            drawing.locked ? 'khóa' : '',
        ].filter(Boolean);
        if (drawing.type === 'hline' || drawing.type === 'alert') {
            flags.unshift(formatPrice(drawing.price, state.activeMarketData?.symbol));
        } else if (drawing.type === 'vline') {
            const bar = state.activeMarketData?.bars?.[Math.round(drawing.barIndex)];
            flags.unshift(formatChartTime(bar?.time_iso || bar?.time));
        } else if (drawing.type === 'position') {
            flags.unshift(`RR 1:${formatFixed(positionRewardRisk(drawing), 2)}`);
        }
        return flags.filter(Boolean).join(' | ') || 'Đang hoạt động';
    }

    function defaultDrawingColor(tool) {
        const map = {
            rect: '#b36b00',
            'rotated-rect': '#b36b00',
            circle: '#0f766e',
            ellipse: '#0f766e',
            'triangle-shape': '#0f766e',
            fib: '#7c3aed',
            'fib-extension': '#7c3aed',
            'fib-channel': '#7c3aed',
            'fib-time-zone': '#7c3aed',
            'fib-fan': '#7c3aed',
            'fib-circle': '#7c3aed',
            'fib-wedge': '#7c3aed',
            'gann-fan': '#8b5cf6',
            'gann-square': '#8b5cf6',
            'gann-box': '#8b5cf6',
            measure: '#0f766e',
            ray: '#0ea5e9',
            trendline: '#1f6feb',
            arrow: '#bc3d3d',
            'arrow-marker': '#bc3d3d',
            'up-arrow': '#16845f',
            'down-arrow': '#bc3d3d',
            brush: '#1f6feb',
            highlighter: '#f59e0b',
            alert: '#bc3d3d',
        };
        return map[tool] || '#1f6feb';
    }

    function activeBacktestRun(analytics) {
        return analytics?.runs?.find((run) => run.id === state.chartView.activeRunId) || analytics?.runs?.find((run) => run.id === 'standard') || analytics?.runs?.[0] || null;
    }

    function filteredTrades(trades) {
        const filter = state.chartView.tradeFilter || 'all';
        return (trades || []).filter((trade) => {
            if (filter === 'win') return trade.netR > 0;
            if (filter === 'loss') return trade.netR < 0;
            if (filter === 'long') return trade.direction === 'long';
            if (filter === 'short') return trade.direction === 'short';
            return true;
        });
    }

    function selectedTrade(run) {
        const trades = filteredTrades(run?.trades || []);
        return trades.find((trade) => trade.id === state.chartView.selectedTradeId) || trades[trades.length - 1] || null;
    }

    function ensureSelectedTrade(analytics) {
        const run = activeBacktestRun(analytics);
        const trades = filteredTrades(run?.trades || []);
        if (!trades.length) {
            state.chartView.selectedTradeId = null;
            return;
        }
        if (!trades.some((trade) => trade.id === state.chartView.selectedTradeId)) {
            state.chartView.selectedTradeId = trades[trades.length - 1].id;
        }
    }

        function selectTrade(id, focus = false) {
        state.chartView.selectedTradeId = id;
        const analytics = state.activeMarketData ? getChartAnalytics(state.activeMarketData) : null;
        const run = activeBacktestRun(analytics);
        const trade = run?.trades?.find((item) => item.id === id);
        if (focus && trade) {
            focusTrade(trade);
            if (state.activeTab === 'pa-training') renderPaTraining();
            return;
        }
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        if (state.activeTab === 'pa-training') renderPaTraining();
    }

    function focusTrade(trade) {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length || !trade) return;
        const span = clamp(Math.max(40, (trade.exitIndex - trade.entryIndex + 1) * 4), 40, bars.length);
        const first = clamp(Math.round(trade.entryIndex - span * 0.35), 0, Math.max(0, bars.length - span));
        const offsetLimits = chartRightOffsetLimits(bars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(bars.length - first - span, offsetLimits.min, offsetLimits.max);
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        state.chartView.selectedTradeId = trade.id;
        drawMarketChart(state.activeMarketData);
    }

    function previousTrade() {
        moveSelectedTrade(-1);
    }

    function nextTradeSelection() {
        moveSelectedTrade(1);
    }

    function moveSelectedTrade(delta) {
        if (!state.activeMarketData) return;
        const run = activeBacktestRun(getChartAnalytics(state.activeMarketData));
        const trades = filteredTrades(run?.trades || []);
        if (!trades.length) return;
        const current = Math.max(0, trades.findIndex((trade) => trade.id === state.chartView.selectedTradeId));
        const nextIndex = clamp(current + delta, 0, trades.length - 1);
        focusTrade(trades[nextIndex]);
    }

    function fitFullChart() {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        state.chartView.barsOnScreen = bars.length;
        state.chartView.rightOffset = 0;
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        drawMarketChart(state.activeMarketData);
        if (state.activeTab === 'pa-training') persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
    }

    function fitChartHorizontally() {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        state.chartView.barsOnScreen = bars.length;
        state.chartView.rightOffset = 0;
        state.chartView.draftDrawing = null;
        hideChartQuickMenu();
        drawMarketChart(state.activeMarketData);
        if (state.activeTab === 'pa-training') persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
    }

    function fitChartVertically() {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        state.chartView.draftDrawing = null;
        hideChartQuickMenu();
        drawMarketChart(state.activeMarketData);
        if (state.activeTab === 'pa-training') persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
    }

    function jumpChartToBoundary(where) {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        const minBars = Math.min(12, bars.length);
        const span = clamp(Math.round(state.chartView.barsOnScreen || 180), minBars, bars.length);
        const offsetLimits = chartRightOffsetLimits(bars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = where === 'start'
            ? clamp(bars.length - span, offsetLimits.min, offsetLimits.max)
            : 0;
        state.chartView.priceOffset = 0;
        state.chartView.draftDrawing = null;
        hideChartQuickMenu();
        drawMarketChart(state.activeMarketData);
        if (state.activeTab === 'pa-training') persistPaWorkspace({ chartTimeframe: state.activeMarketData.timeframe });
    }

    function copyTradeInfo(trade) {
        const text = tradeDetailText(trade);
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(text).then(() => showToast('Đã sao chép thông tin lệnh')).catch(() => showToast(text));
            return;
        }
        showToast(text);
    }

    function exportTradeJson(trade) {
        const blob = new Blob([JSON.stringify(trade, null, 2)], { type: 'application/json;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${trade.symbol}_${trade.id}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function takeChartScreenshot() {
        const canvas = $('#marketChart');
        if (!canvas) return;
        const a = document.createElement('a');
        a.href = canvas.toDataURL('image/png');
        a.download = `${state.activeMarketData?.symbol || 'chart'}_${state.activeMarketData?.timeframe || 'TF'}_backtest.png`;
        a.click();
    }

    function toggleChartTheme() {
        document.body.classList.toggle('is-dark');
        renderChartControlState();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function replayCutoffIndex(data) {
        const bars = data?.bars || [];
        if (!state.chartView.replay.enabled) return Number.POSITIVE_INFINITY;
        if (state.chartView.replay.time !== null && Number.isFinite(Number(state.chartView.replay.time))) {
            return chartIndexAtOrBeforeTime(bars, Number(state.chartView.replay.time), state.chartView.replay.index);
        }
        return clamp(Math.round(state.chartView.replay.index || 0), 0, Math.max(0, bars.length - 1));
    }

    function setChartReplayIndex(index, follow = true) {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        state.chartView.replay.enabled = true;
        state.chartView.replay.index = clamp(Math.round(index), 0, bars.length - 1);
        state.chartView.replay.time = barTimeValue(bars[state.chartView.replay.index]) + timeframeSeconds(state.activeMarketData?.timeframe) - 1;
        if (state.activeTab === 'pa-training') {
            persistPaWorkspace({ replayIndex: state.chartView.replay.index, replayEnabled: true });
        }
        // Playback advances the hidden-data boundary without changing the user's
        // chart viewport. Explicit navigation controls may still center it.
        if (follow === true && !state.chartView.replay.playing) centerChartOnIndex(state.chartView.replay.index);
        drawMarketChart(state.activeMarketData);
    }

    function centerChartOnIndex(index) {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        const span = clamp(state.chartView.barsOnScreen || 180, Math.min(12, bars.length), bars.length);
        const first = clamp(Math.round(index - span * 0.65), 0, Math.max(0, bars.length - span));
        const offsetLimits = chartRightOffsetLimits(bars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(bars.length - first - span, offsetLimits.min, offsetLimits.max);
    }

    function toggleChartReplay() {
        const replay = state.chartView.replay;
        if (replay.playing) {
            stopChartReplay(false);
            drawMarketChart(state.activeMarketData);
            return;
        }
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        replay.enabled = true;
        if (!Number.isFinite(replay.index) || replay.index >= bars.length - 1) {
            replay.index = 0;
            replay.time = null;
        }
        if (replay.time === null || !Number.isFinite(Number(replay.time))) {
            replay.time = barTimeValue(bars[replay.index]) + timeframeSeconds(state.activeMarketData?.timeframe) - 1;
        }
        replay.playing = true;
        replay.timer = setInterval(() => {
            const step = Math.max(1, Math.round(replay.speed || 1));
            if (replay.index >= bars.length - 1) {
                stopChartReplay(false);
                drawMarketChart(state.activeMarketData);
                return;
            }
            setChartReplayIndex(Math.min(bars.length - 1, replay.index + step), false);
        }, Math.max(80, 520 / Math.max(1, replay.speed || 1)));
        drawMarketChart(state.activeMarketData);
    }

    function stopChartReplay(reset = false) {
        const replay = state.chartView.replay;
        if (replay.timer) clearInterval(replay.timer);
        replay.timer = null;
        replay.playing = false;
        if (reset) {
            replay.enabled = false;
            replay.index = 0;
            replay.time = null;
        }
        renderReplayControls(state.activeMarketData);
    }

    function changeChartReplaySpeed(delta) {
        const speeds = [0.5, 1, 2, 4, 8, 16];
        const current = Number(state.chartView.replay.speed || 1);
        const currentIndex = Math.max(0, speeds.findIndex((value) => value >= current));
        const next = speeds[clamp(currentIndex + delta, 0, speeds.length - 1)] || 1;
        state.chartView.replay.speed = next;
        const select = $('#chartReplaySpeed');
        if (select) select.value = String(next);
        if (state.chartView.replay.playing) {
            stopChartReplay(false);
            toggleChartReplay();
        }
        renderReplayControls(state.activeMarketData);
        showToast(`Tốc độ chạy nến: ${next}x`);
    }
    function renderReplayControls(data) {
        syncPaWorkspaceUi(data);
        const bars = data?.bars || [];
        const range = $('#chartReplayRange');
        const label = $('#chartReplayLabel');
        const play = $('#chartReplayPlayBtn');
        const speed = $('#chartReplaySpeed');
        const last = Math.max(0, bars.length - 1);
        const index = clamp(Math.round(state.chartView.replay.index || 0), 0, last);
        if (range) {
            range.max = String(last);
            range.value = String(index);
        }
        if (speed) speed.value = String(state.chartView.replay.speed || 1);
        if (label) {
            const bar = bars[index] || bars[bars.length - 1];
            label.textContent = bars.length ? `#${number(index + 1)}/${number(bars.length)} · ${formatChartTime(bar?.time_iso || bar?.time)} · ${state.chartView.replay.speed || 1}x` : 'Chưa có dữ liệu';
        }
        if (play) play.textContent = state.chartView.replay.playing ? '⏸' : '▶';
    }
    function sma(values, period) {
        const result = Array(values.length).fill(null);
        let sum = 0;
        const queue = [];
        values.forEach((value, index) => {
            const n = Number(value);
            queue.push(Number.isFinite(n) ? n : null);
            if (Number.isFinite(n)) sum += n;
            if (queue.length > period) {
                const removed = queue.shift();
                if (Number.isFinite(removed)) sum -= removed;
            }
            if (queue.length === period && queue.every(Number.isFinite)) {
                result[index] = sum / period;
            }
        });
        return result;
    }

    function ema(values, period) {
        const result = Array(values.length).fill(null);
        const k = 2 / (period + 1);
        let sum = 0;
        let count = 0;
        let current = null;
        values.forEach((value, index) => {
            const n = Number(value);
            if (!Number.isFinite(n)) return;
            if (current === null) {
                sum += n;
                count++;
                if (count === period) {
                    current = sum / period;
                    result[index] = current;
                }
                return;
            }
            current = n * k + current * (1 - k);
            result[index] = current;
        });
        return result;
    }

    function rsi(values, period) {
        const result = Array(values.length).fill(null);
        let gain = 0;
        let loss = 0;
        for (let i = 1; i < values.length; i++) {
            const change = Number(values[i]) - Number(values[i - 1]);
            const up = Math.max(0, change);
            const down = Math.max(0, -change);
            if (i <= period) {
                gain += up;
                loss += down;
                if (i === period) {
                    gain /= period;
                    loss /= period;
                    result[i] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
                }
            } else {
                gain = (gain * (period - 1) + up) / period;
                loss = (loss * (period - 1) + down) / period;
                result[i] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
            }
        }
        return result;
    }

    function atr(bars, period) {
        const tr = bars.map((bar, index) => {
            const high = Number(bar.high);
            const low = Number(bar.low);
            const prevClose = index > 0 ? Number(bars[index - 1].close) : Number(bar.close);
            if (![high, low, prevClose].every(Number.isFinite)) return null;
            return Math.max(high - low, Math.abs(high - prevClose), Math.abs(low - prevClose));
        });
        return ema(tr, period);
    }

    function macdSeries(values, fastPeriod, slowPeriod, signalPeriod) {
        const fast = ema(values, fastPeriod);
        const slow = ema(values, slowPeriod);
        const line = values.map((_, index) => {
            if (!Number.isFinite(fast[index]) || !Number.isFinite(slow[index])) return null;
            return fast[index] - slow[index];
        });
        const signal = ema(line, signalPeriod);
        const hist = values.map((_, index) => {
            if (!Number.isFinite(line[index]) || !Number.isFinite(signal[index])) return null;
            return line[index] - signal[index];
        });
        return { line, signal, hist };
    }

    function bollingerBands(values, period, deviation) {
        const middle = sma(values, period);
        const upper = Array(values.length).fill(null);
        const lower = Array(values.length).fill(null);
        for (let i = period - 1; i < values.length; i++) {
            const slice = values.slice(i - period + 1, i + 1).map(Number);
            if (!slice.every(Number.isFinite)) continue;
            const mean = middle[i];
            const variance = slice.reduce((sum, value) => sum + (value - mean) ** 2, 0) / period;
            const sd = Math.sqrt(variance);
            upper[i] = mean + sd * deviation;
            lower[i] = mean - sd * deviation;
        }
        return { middle, upper, lower };
    }

    function vwap(bars) {
        const result = Array(bars.length).fill(null);
        let pv = 0;
        let volumeSum = 0;
        bars.forEach((bar, index) => {
            const typical = (Number(bar.high) + Number(bar.low) + Number(bar.close)) / 3;
            const volume = Math.max(1, Number(bar.tick_volume || bar.real_volume || 1));
            if (!Number.isFinite(typical)) return;
            pv += typical * volume;
            volumeSum += volume;
            result[index] = pv / volumeSum;
        });
        return result;
    }

    function atrStop(closes, trend, atrValues, multiplier) {
        return closes.map((close, index) => {
            const atrValue = atrValues[index];
            const trendValue = trend[index];
            if (![close, atrValue, trendValue].every(Number.isFinite)) return null;
            return close >= trendValue ? close - atrValue * multiplier : close + atrValue * multiplier;
        });
    }

    function median(values) {
        const sorted = values.filter(Number.isFinite).slice().sort((a, b) => a - b);
        if (!sorted.length) return 0;
        const mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    }

    function barTimeValue(bar) {
        if (!bar) return NaN;
        if (Number.isFinite(Number(bar.time))) return Number(bar.time);
        const date = new Date(bar.time_iso || bar.time);
        return Number.isNaN(date.getTime()) ? NaN : Math.floor(date.getTime() / 1000);
    }

    function timeframeSeconds(timeframe) {
        return ({ M1: 60, M5: 300, M15: 900, M30: 1800, H1: 3600, H4: 14400, D1: 86400, W1: 604800, MN1: 2592000 })[String(timeframe || '').toUpperCase()] || 900;
    }

    function bodyIsDark() {
        return document.body.classList.contains('is-dark');
    }

    function backtestMetricHtml(label, value) {
        return `<div class="backtest-metric"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
    }

    function tradeDetailRow(label, value) {
        return `<div class="trade-detail-row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
    }

    function tradeDetailText(trade) {
        return [
            `Cặp: ${trade.symbol}`,
            `Hướng: ${displayTradeSide(trade.direction)}`,
            `Giá vào: ${formatPrice(trade.entryPrice, trade.symbol)} - ${formatChartTime(trade.entryTime)}`,
            `Giá thoát: ${formatPrice(trade.exitPrice, trade.symbol)} - ${formatChartTime(trade.exitTime)}`,
            `Kết quả: ${formatSigned(trade.netR, 2)}R (${money(trade.pnlUsd)})`,
            `MFE/MAE: ${formatFixed(trade.mfeR, 2)}R / ${formatFixed(trade.maeR, 2)}R`,
            `Lý do vào: ${translateDisplayText(trade.entryReason)}`,
            `Lý do thoát: ${translateDisplayText(trade.exitReason)}`,
        ].join('\n');
    }

    function selectedAccount() {
        return state.accounts.find((account) => account.id === state.selectedAccountId) || state.accounts[0];
    }

    function selectedStrategyVersion() {
        return findStrategyVersion(state.selectedStrategyVersionId) || state.strategyVersions?.[0] || null;
    }

    function findStrategyVersion(id) {
        return (state.strategyVersions || []).find((version) => version.id === id) || null;
    }

    function selectedStrategyV2Version() {
        return findStrategyV2Version(state.selectedStrategyV2Id) || state.strategyV2Versions?.[0] || null;
    }

    function findStrategyV2Version(id) {
        return (state.strategyV2Versions || []).find((version) => version.id === id) || null;
    }

    function activeCase() {
        return state.candidateCases[state.activeCaseIndex];
    }

    function previousCase() {
        if (!state.candidateCases.length) return;
        state.activeCaseIndex = (state.activeCaseIndex - 1 + state.candidateCases.length) % state.candidateCases.length;
        state.activeScore = null;
        renderTrainer();
    }

    function nextCase() {
        if (!state.candidateCases.length) return;
        state.activeCaseIndex = (state.activeCaseIndex + 1) % state.candidateCases.length;
        state.activeScore = null;
    }

    function setNavSectionOpen(section, isOpen) {
        const toggle = section.querySelector('.nav-section-toggle');
        const body = section.querySelector('.nav-section-body');
        if (!toggle || !body) return;
        section.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        body.hidden = !isOpen;
    }

    function revealNavSectionForTab(tab) {
        const activeButton = $$('.nav-tab').find((button) => button.dataset.tab === tab);
        const section = activeButton?.closest('.nav-section');
        if (section) setNavSectionOpen(section, true);
    }

    function bindNavSectionToggles() {
        $$('.nav-section').forEach((section) => {
            const toggle = section.querySelector('.nav-section-toggle');
            if (!toggle) return;
            setNavSectionOpen(section, toggle.getAttribute('aria-expanded') === 'true');
            toggle.addEventListener('click', () => {
                setNavSectionOpen(section, toggle.getAttribute('aria-expanded') !== 'true');
            });
        });
    }

    function bindEvents() {
        bindHelpEvents();
        bindNavSectionToggles();
        $$('.nav-tab').forEach((button) => {
            button.addEventListener('click', () => activateTab(button.dataset.tab));
        });
        $('#paJournalSearch')?.addEventListener('input', (event) => {
            state.paJournalSearch = event.target.value || '';
            state.paJournalPage = 0;
            renderPaJournal();
        });
        $('#paJournalPeriod')?.addEventListener('change', (event) => {
            state.paJournalPeriod = event.target.value || '30';
            state.paJournalPage = 0;
            renderPaJournal();
        });
        $('#paJournalRefreshBtn')?.addEventListener('click', () => loadPaJournalMemory().catch((error) => showToast(error.message)));
        $('#paJournalExportBtn')?.addEventListener('click', exportPaJournal);
        $('#paJournalTableRegion')?.addEventListener('click', (event) => {
            const pageButton = event.target.closest('[data-pa-journal-page]');
            if (pageButton) {
                state.paJournalPage += pageButton.dataset.paJournalPage === 'next' ? 1 : -1;
                renderPaJournal();
                return;
            }
            const row = event.target.closest('tr[data-pa-journal-id]');
            if (!row) return;
            state.paJournalSelectedId = row.dataset.paJournalId;
            renderPaJournal();
        });
        $('#paJournalTableRegion')?.addEventListener('keydown', (event) => {
            if (!['Enter', ' '].includes(event.key)) return;
            const row = event.target.closest('tr[data-pa-journal-id]');
            if (!row) return;
            event.preventDefault();
            state.paJournalSelectedId = row.dataset.paJournalId;
            renderPaJournal();
        });
        $$('.pa-journal-tab').forEach((button) => button.addEventListener('click', () => {
            state.paJournalView = button.dataset.paJournalView || 'trades';
            state.paJournalSelectedId = '';
            state.paJournalPage = 0;
            renderPaJournal();
        }));
        $$('[data-strategy-builder-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                state.strategyBuilderMode = button.dataset.strategyBuilderMode === 'ai' ? 'ai' : 'manual';
                renderStrategyBuilderMode();
                if (state.strategyBuilderMode === 'ai') {
                    renderAutoResearch();
                }
            });
        });
        window.addEventListener('hashchange', activateTabFromHash);
        $('#refreshBtn').addEventListener('click', () => bootstrap().catch((e) => showToast(e.message)));
        $('#newAccountBtn').addEventListener('click', createAccount);
        $('#saveAccountsBtn').addEventListener('click', saveSelectedAccount);
        $('#saveStrategyBtn').addEventListener('click', saveStrategy);
        $('#createStrategyV1Btn').addEventListener('click', () => createStrategyV1().catch((e) => showToast(e.message)));
        $('#refreshStrategyVersionsBtn').addEventListener('click', () => refreshStrategyVersions().catch((e) => showToast(e.message)));
        $('#refreshStrategyV2Btn')?.addEventListener('click', () => loadStrategyV2Versions().catch((e) => showToast(e.message)));
        $('#newStrategyV2Btn')?.addEventListener('click', clearStrategyV2Form);
        $('#createStrategyV2Btn')?.addEventListener('click', () => createStrategyV2().catch((e) => showToast(e.message)));
        $('#updateStrategyV2Btn')?.addEventListener('click', () => updateStrategyV2().catch((e) => showToast(e.message)));
        $('#cloneStrategyV2Btn')?.addEventListener('click', () => cloneStrategyV2().catch((e) => showToast(e.message)));
        $('#runStrategyV2BacktestBtn')?.addEventListener('click', () => runStrategyV2Backtest().catch((e) => showToast(e.message)));
        $('#approveStrategyV2Btn')?.addEventListener('click', () => approveStrategyV2(false).catch((e) => showToast(e.message)));
        $('#promoteStrategyV2Btn')?.addEventListener('click', () => approveStrategyV2(true).catch((e) => showToast(e.message)));
        $('#archiveStrategyV2Btn')?.addEventListener('click', () => archiveStrategyV2().catch((e) => showToast(e.message)));
        $('#runServerBacktestBtn').addEventListener('click', () => runServerBacktest().catch((e) => showToast(e.message)));
        $('#refreshBacktestV2Btn')?.addEventListener('click', () => loadBacktestV2Results().catch((e) => showToast(e.message)));
        $('#backtestV2VersionFilter')?.addEventListener('change', (event) => {
            state.backtestV2FilterVersionId = event.target.value;
            state.selectedBacktestV2Id = '';
            loadBacktestV2Results().catch((e) => showToast(e.message));
        });
        $('#refreshKPIProfilesBtn')?.addEventListener('click', () => loadKPIProfiles().catch((e) => showToast(e.message)));
        $('#newKPIProfileBtn')?.addEventListener('click', clearKPIProfileForm);
        $('#kpiProfileForm')?.addEventListener('submit', (event) => saveKPIProfile(event).catch((e) => showToast(e.message)));
        $('#setDefaultKPIProfileBtn')?.addEventListener('click', () => setDefaultKPIProfile().catch((e) => showToast(e.message)));
        $('#deleteKPIProfileBtn')?.addEventListener('click', () => deleteKPIProfile().catch((e) => showToast(e.message)));
        $('#refreshExperimentsBtn').addEventListener('click', () => loadExperiments().catch((e) => showToast(e.message)));
        $('#experimentCreateForm').addEventListener('submit', (event) => createExperiment(event).catch((e) => showToast(e.message)));
        $$('[data-experiment-action]').forEach((button) => {
            button.addEventListener('click', () => changeExperimentStatus(button.dataset.experimentAction).catch((e) => showToast(e.message)));
        });
        $('#refreshAIJobsBtn')?.addEventListener('click', () => loadAIJobs().catch((e) => showToast(e.message)));
        $('#aiJobCreateForm')?.addEventListener('submit', (event) => createAIJob(event).catch((e) => showToast(e.message)));
        $('#cancelAIJobBtn')?.addEventListener('click', () => cancelAIJob().catch((e) => showToast(e.message)));
        $('#runResearchMockOnceBtn')?.addEventListener('click', () => runResearchMockOnce().catch((e) => showToast(e.message)));
        $('#runAIWorkerOnceBtn')?.addEventListener('click', () => runAIWorkerOnce().catch((e) => showToast(e.message)));
        $('#createAIJobStrategyDraftBtn')?.addEventListener('click', () => createStrategyDraftFromAIJob().catch((e) => showToast(e.message)));
        $('#runAutoResearchBtn')?.addEventListener('click', () => runAutoResearch().catch((e) => showToast(e.message)));
        $('#stopAutoResearchBtn')?.addEventListener('click', requestAutoResearchStop);
        $('#refreshAutoResearchBtn')?.addEventListener('click', () => loadAutoResearchRuns().catch((e) => showToast(e.message)));
        $('#createResearchLabBtn')?.addEventListener('click', () => createResearchLabSession().catch((e) => showToast(e.message)));
        $('#refreshResearchLabBtn')?.addEventListener('click', () => loadResearchLabSessions().catch((e) => showToast(e.message)));
        $('#runResearchLabOneBtn')?.addEventListener('click', () => runResearchLabOne().catch((e) => showToast(e.message)));
        $('#runResearchLabLoopBtn')?.addEventListener('click', () => runResearchLabLoop().catch((e) => showToast(e.message)));
        $('#stopResearchLabBtn')?.addEventListener('click', requestResearchLabStop);
        $('#researchLabSymbolSelect')?.addEventListener('change', renderResearchLab);
        $('#researchLabMethodSelect')?.addEventListener('change', renderResearchLab);
        $('#researchLabAiProviderSelect')?.addEventListener('change', renderResearchLab);
        $('#autoResearchModeSelect')?.addEventListener('change', renderAutoResearch);
        $('#autoResearchAiProviderSelect')?.addEventListener('change', () => {
            state.autoResearchAiProviderTouched = true;
            renderAutoResearch();
        });
        $('#autoResearchSymbolSelect')?.addEventListener('change', renderAutoResearch);
        $('#autoResearchMethodSelect')?.addEventListener('change', renderAutoResearch);
        $('#autoResearchTournamentToggle')?.addEventListener('change', renderAutoResearch);
        $('#autoResearchSelectAllSymbolsBtn')?.addEventListener('click', () => {
            setAutoResearchSelectedSymbols(autoResearchSymbols(), true);
            renderAutoResearch();
        });
        $('#autoResearchClearSymbolsBtn')?.addEventListener('click', () => {
            setAutoResearchSelectedSymbols([], true);
            renderAutoResearch();
        });
                $('#paTrainingZonesBtn')?.addEventListener('click', () => {
            state.paTrainingMode = 'cases';
            renderPaTraining();
        });
        $('#paTrainingLoadZonesBtn')?.addEventListener('click', () => loadPaTrainingTradeCases().catch((e) => showToast(e.message)));
        $('#paTrainingStartCasesBtn')?.addEventListener('click', () => startPaTrainingCaseQueue().catch((e) => showToast(e.message)));
        $('#paTrainingRefreshBtn')?.addEventListener('click', () => loadPaTrainingMemory().catch((e) => showToast(e.message)));
        $('#paTrainingChartH4Btn')?.addEventListener('click', () => openPaTrainingChart('H4').catch((e) => showToast(e.message)));
        $('#paTrainingChartM15Btn')?.addEventListener('click', () => openPaTrainingChart('M15').catch((e) => showToast(e.message)));
        $('#paTrainingChartOpenMt5Btn')?.addEventListener('click', () => openPaTrainingFullChart().catch((e) => showToast(e.message)));
        $('#paTrainingSymbolSelect')?.addEventListener('change', (event) => changePaTrainingSymbol(event.target.value));
        $('#paTrainingSymbolSearch')?.addEventListener('change', (event) => changePaTrainingSymbol(event.target.value));
        $('#paTrainingSymbolSearch')?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                changePaTrainingSymbol(event.target.value);
            }
        });
        $('#paTrainingTimeframeSelect')?.addEventListener('change', (event) => {
            state.paTrainingTimeframe = event.target.value || 'H4';
            state.paTrainingZones = [];
            state.paTrainingTradeCases = [];
            state.paTrainingSelectedCaseId = '';
            state.paTrainingSkippedCaseIds = [];
            state.paTrainingQueueLoaded = false;
            state.paTrainingSelectedZoneId = '';
            renderPaTraining();
        });
        $('#paTrainingZoneFilter')?.addEventListener('change', (event) => {
            state.paTrainingZoneFilter = event.target.value || 'active';
            state.paTrainingSelectedCaseId = '';
            if (state.paTrainingQueueLoaded) loadPaTrainingTradeCases({ silent: true }).catch((e) => showToast(e.message));
            else renderPaTraining();
        });
        $('#paTrainingChatSendBtn')?.addEventListener('click', () => sendPaTrainingChat().catch((e) => showToast(e.message)));
        $('#paTrainingChatInput')?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendPaTrainingChat().catch((e) => showToast(e.message));
            }
        });        $$('[data-pa-chat-prompt]').forEach((button) => {
            button.addEventListener('click', (event) => {
                runPaActionButtonClick(button, event, () => {
                    const prompt = button.dataset.paChatPrompt || '';
                    const input = $('#paTrainingChatInput');
                    if (prompt.endsWith('Lý do: ')) {
                        if (input) {
                            input.value = prompt;
                            input.focus();
                        }
                        return;
                    }
                    sendPaTrainingChat(prompt).catch((e) => showToast(e.message));
                });
            });
        });
        $$('[data-pa-case-decision]').forEach((button) => {
            button.addEventListener('click', (event) => {
                runPaActionButtonClick(button, event, () => {
                    if (button.dataset.paCaseDecision === 'weak_case') {
                        activatePaTrainingLevelDrag();
                        return;
                    }
                    savePaTrainingCaseDecision(button.dataset.paCaseDecision, button.dataset.paCaseLabel || button.dataset.paCaseDecision).catch((e) => showToast(e.message));
                });
            });
        });
        $('#paTrainingCoachZoneSummary')?.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-pa-case-details-toggle]');
            if (toggle) {
                event.preventDefault();
                state.paTrainingExpandedCaseId = toggle.getAttribute('aria-expanded') === 'true' ? '' : paTrainingSessionKey();
                renderPaTrainingCoachZoneSummary();
                return;
            }
            const button = event.target.closest('[data-pa-case-level-reset]');
            if (!button) return;
            event.preventDefault();
            resetPaTrainingCaseLevels();
        });
        $$('[data-pa-case-skip]').forEach((button) => {
            button.addEventListener('click', (event) => {
                runPaActionButtonClick(button, event, skipPaTrainingCurrentCase);
            });
        });
        $$('[data-pa-lesson-save]').forEach((button) => {
            button.addEventListener('click', (event) => runPaActionButtonClick(button, event, () => savePaTrainingLessonFromChat().catch((e) => showToast(e.message))));
        });
        $$('[data-pa-session-save]').forEach((button) => {
            button.addEventListener('click', (event) => runPaActionButtonClick(button, event, () => savePaTrainingCurrentSession().catch((e) => showToast(e.message))));
        });
        $('#openAiSettingsForm')?.addEventListener('submit', (event) => saveOpenAiSettings(event).catch((e) => showToast(e.message)));
        $('#refreshOpenAiSettingsBtn')?.addEventListener('click', () => loadOpenAiSettings().catch((e) => showToast(e.message)));
        $('#editOpenAiKeyBtn')?.addEventListener('click', focusOpenAiKeyInput);
        $('#deleteOpenAiKeyBtn')?.addEventListener('click', () => deleteOpenAiApiKey().catch((e) => showToast(e.message)));
        $('#testOpenAiSettingsBtn')?.addEventListener('click', () => testOpenAiSettings().catch((e) => showToast(e.message)));
        $('#editOpenAiAdminKeyBtn')?.addEventListener('click', focusOpenAiAdminKeyInput);
        $('#saveOpenAiAdminKeyBtn')?.addEventListener('click', () => saveOpenAiAdminKey().catch((e) => showToast(e.message)));
        $('#deleteOpenAiAdminKeyBtn')?.addEventListener('click', () => deleteOpenAiAdminKey().catch((e) => showToast(e.message)));
        $('#syncOpenAiOfficialCostsBtn')?.addEventListener('click', () => syncOpenAiOfficialCosts().catch((e) => showToast(e.message)));
        $('#refreshPromptTemplatesBtn')?.addEventListener('click', () => loadPromptTemplates().catch((e) => showToast(e.message)));
        $('#newPromptTemplateBtn')?.addEventListener('click', clearPromptTemplateForm);
        $('#promptTemplateForm')?.addEventListener('submit', (event) => savePromptTemplate(event).catch((e) => showToast(e.message)));
        $('#activatePromptTemplateBtn')?.addEventListener('click', () => activatePromptTemplate().catch((e) => showToast(e.message)));
        $('#archivePromptTemplateBtn')?.addEventListener('click', () => archivePromptTemplate().catch((e) => showToast(e.message)));
        $('#testPromptMockAiBtn')?.addEventListener('click', () => testPromptWithMockAI().catch((e) => showToast(e.message)));
        $('#promptGatewayProviderSelect')?.addEventListener('change', (event) => {
            state.promptGatewayProvider = ['openai_chat', 'ollama'].includes(event.target.value) ? event.target.value : 'mock';
            state.promptGatewayTestResult = null;
            state.promptGatewayTestError = '';
            renderPromptGatewayTester(selectedPromptTemplate());
        });
        $('#promptGatewayVariablesInput')?.addEventListener('input', (event) => {
            state.promptGatewaySampleVariables = event.target.value;
        });
        $('#loadCalibrationQueueBtn').addEventListener('click', () => loadCalibrationQueue().catch((e) => showToast(e.message)));
        $('#calibrationPrevBtn').addEventListener('click', () => {
            if (!state.calibrationQueue.length) return;
            state.calibrationIndex = (state.calibrationIndex - 1 + state.calibrationQueue.length) % state.calibrationQueue.length;
            renderCalibrationReview();
        });
        $('#calibrationNextBtn').addEventListener('click', () => {
            if (!state.calibrationQueue.length) return;
            state.calibrationIndex = (state.calibrationIndex + 1) % state.calibrationQueue.length;
            renderCalibrationReview();
        });
        $('#loadCalibrationReportBtn').addEventListener('click', () => loadCalibrationReport().catch((e) => showToast(e.message)));
        $('#loadCalibrationAdvisorBtn').addEventListener('click', () => loadCalibrationAdvisor().catch((e) => showToast(e.message)));
        $('#generateCalibratedPresetBtn').addEventListener('click', () => generateCalibratedPreset().catch((e) => showToast(e.message)));
        $('#runEvolutionBtn').addEventListener('click', () => runStrategyEvolution().catch((e) => showToast(e.message)));
        $('#refreshCompareBtn').addEventListener('click', () => bootstrap().catch((e) => showToast(e.message)));
        $('#backtestVersionSelect').addEventListener('change', (event) => {
            state.selectedStrategyVersionId = event.target.value;
            renderBacktestLab();
        });
        $('#evolutionParentSelect').addEventListener('change', (event) => {
            state.selectedStrategyVersionId = event.target.value;
            renderStrategyEvolution();
        });
        $('#calibrationVersionSelect').addEventListener('change', (event) => {
            state.selectedStrategyVersionId = event.target.value;
            renderCalibrationReview();
        });
        $('#calibrationQueueTypeSelect').addEventListener('change', (event) => {
            state.calibrationQueueType = event.target.value;
        });
        $('#backtestSymbolSelect').addEventListener('change', () => checkBacktestDataQuality().catch((e) => showToast(e.message)));
        $('#tradebrainMemoryTypeFilter').addEventListener('change', renderTradeBrainMemory);
        $('#tradebrainMemoryVersionFilter').addEventListener('change', renderTradeBrainMemory);
        $('#runEvalBtn').addEventListener('click', () => runEvaluation().catch((e) => showToast(e.message)));
        $('#mt5StatusBtn').addEventListener('click', () => mt5Status().catch((e) => showToast(e.message)));
        $('#mt5FetchBtn').addEventListener('click', () => mt5Fetch().catch((e) => showToast(e.message)));
        $('#mt5RefreshFilesBtn').addEventListener('click', () => refreshMarketFiles().catch((e) => showToast(e.message)));
        ['mt5TerminalPath', 'mt5Symbols', 'mt5Bars'].forEach((id) => {
            document.getElementById(id).addEventListener('input', handleMt5FormChange);
        });
        $$('[data-mt5-timeframe]').forEach((input) => {
            input.addEventListener('change', handleMt5FormChange);
        });
        bindChartEvents();
        $('#scoreCaseBtn').addEventListener('click', () => scoreActiveCase().catch((e) => showToast(e.message)));
        $('#prevCaseBtn').addEventListener('click', previousCase);
        $('#nextCaseBtn').addEventListener('click', () => {
            nextCase();
            renderTrainer();
        });
        $$('[data-review]').forEach((button) => {
            button.addEventListener('click', () => reviewActiveCase(button.dataset.review).catch((e) => showToast(e.message)));
        });
        $('#memoryImport').addEventListener('change', importMemory);
        window.addEventListener('resize', debounce(() => {
            if ($('#tab-trainer').classList.contains('is-active') && activeCase()) {
                drawCaseChart(readEditedCase());
            }
            if ($('#tab-mt5').classList.contains('is-active') && state.activeMarketData) {
                drawMarketChart(state.activeMarketData);
            }
        }, 120));
    }

    function handleMt5FormChange() {
        persistMt5Form();
        updateMt5BarsHint();
    }

    function clampPaReplayFloatingPanel() {
        const panel = $('#chartReplayFloatingPanel');
        if (!panel || panel.hidden) return;
        const canvasWrap = panel.closest('.chart-canvas-wrap');
        if (!canvasWrap) return;
        const wrapRect = canvasWrap.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const maxLeft = Math.max(8, wrapRect.width - panelRect.width - 8);
        const maxTop = Math.max(8, wrapRect.height - panelRect.height - 8);
        const currentLeft = Number.parseFloat(panel.style.left || '16');
        const currentTop = Number.parseFloat(panel.style.top || '16');
        panel.style.left = `${Math.min(Math.max(8, currentLeft), maxLeft)}px`;
        panel.style.top = `${Math.min(Math.max(8, currentTop), maxTop)}px`;
    }

    function setPaReplayPanelOpen(open) {
        const panel = $('#chartReplayFloatingPanel');
        const toggle = $('#chartReplayPanelToggleBtn');
        if (!panel) return;
        panel.hidden = !open;
        panel.classList.toggle('is-open', open);
        if (toggle) {
            toggle.classList.toggle('is-active', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (open) {
            requestAnimationFrame(clampPaReplayFloatingPanel);
        }
    }

    function bindPaReplayFloatingPanel() {
        const panel = $('#chartReplayFloatingPanel');
        const handle = $('#chartReplayDragHandle');
        const toggle = $('#chartReplayPanelToggleBtn');
        const close = $('#chartReplayPanelCloseBtn');
        if (!panel || !handle || !toggle || panel.dataset.paReplayBound === '1') return;
        panel.dataset.paReplayBound = '1';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', () => setPaReplayPanelOpen(panel.hidden));
        close?.addEventListener('click', () => setPaReplayPanelOpen(false));
        window.addEventListener('resize', clampPaReplayFloatingPanel);

        let drag = null;
        handle.addEventListener('pointerdown', (event) => {
            if (event.target.closest('button')) return;
            const canvasWrap = panel.closest('.chart-canvas-wrap');
            if (!canvasWrap) return;
            const wrapRect = canvasWrap.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            drag = {
                pointerId: event.pointerId,
                offsetX: event.clientX - panelRect.left,
                offsetY: event.clientY - panelRect.top,
                wrapRect,
            };
            panel.classList.add('is-dragging');
            handle.setPointerCapture(event.pointerId);
        });

        handle.addEventListener('pointermove', (event) => {
            if (!drag || drag.pointerId !== event.pointerId) return;
            const canvasWrap = panel.closest('.chart-canvas-wrap');
            if (!canvasWrap) return;
            const wrapRect = canvasWrap.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            const maxLeft = Math.max(8, wrapRect.width - panelRect.width - 8);
            const maxTop = Math.max(8, wrapRect.height - panelRect.height - 8);
            const nextLeft = Math.min(Math.max(8, event.clientX - wrapRect.left - drag.offsetX), maxLeft);
            const nextTop = Math.min(Math.max(8, event.clientY - wrapRect.top - drag.offsetY), maxTop);
            panel.style.left = `${nextLeft}px`;
            panel.style.top = `${nextTop}px`;
        });

        const stopDrag = (event) => {
            if (!drag || drag.pointerId !== event.pointerId) return;
            drag = null;
            panel.classList.remove('is-dragging');
            try {
                handle.releasePointerCapture(event.pointerId);
            } catch (error) {
                // Pointer capture may already be released by the browser.
            }
        };
        handle.addEventListener('pointerup', stopDrag);
        handle.addEventListener('pointercancel', stopDrag);
    }

    function preparePriceActionChartUi() {
        const controls = $('.chart-controls');
        const drawingToolbar = $('.drawing-toolbar');
        const featureBar = $('#chartFeatureBar');
        if (!controls || !drawingToolbar || !featureBar) return;

        [
            'chartCompareInput',
            'chartAddCompareBtn',
            'chartClearCompareBtn',
            'chartResetBtn',
            'chartScreenshotBtn',
            'chartZoomBoxBtn',
            'drawingLineStyleSelect',
            'drawingCloneBtn',
            'drawingSaveStyleBtn',
        ].forEach((id) => {
            const element = document.getElementById(id);
            if (element) element.hidden = true;
        });

        ['chartMagnetMode', 'chartUndoBtn', 'chartRedoBtn'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) controls.appendChild(element);
        });
        const replayDock = $('#chartReplayFloatingBody');
        const replayGroup = featureBar.querySelector('.replay-group') || document.querySelector('.replay-group');
        if (replayGroup && replayDock && !replayDock.contains(replayGroup)) {
            replayGroup.classList.add('pa-replay-inline', 'pa-replay-floating-controls');
            replayDock.appendChild(replayGroup);
        }
        bindPaReplayFloatingPanel();
        featureBar.hidden = true;

        const rectTool = drawingToolbar.querySelector('[data-chart-tool="rect"]');
        if (rectTool) {
            delete rectTool.dataset.chartTool;
            rectTool.dataset.chartZone = 'supply';
            rectTool.classList.add('zone-rect-tool');
            rectTool.innerHTML = '<span class="zone-tool-icon">▭</span><span>Canh sell</span>';
            rectTool.title = 'Vẽ vùng canh sell: giữ chuột và kéo để tạo hình chữ nhật';
            rectTool.setAttribute('aria-label', 'Vẽ vùng canh sell bằng hình chữ nhật');

            const demandTool = rectTool.cloneNode(true);
            demandTool.dataset.chartZone = 'demand';
            demandTool.innerHTML = '<span class="zone-tool-icon">▭</span><span>Canh buy</span>';
            demandTool.title = 'Vẽ vùng canh buy: giữ chuột và kéo để tạo hình chữ nhật';
            demandTool.setAttribute('aria-label', 'Vẽ vùng canh buy bằng hình chữ nhật');
            rectTool.insertAdjacentElement('afterend', demandTool);
        }

        drawingToolbar.querySelectorAll('[data-chart-tool]').forEach((button) => {
            button.hidden = !PRICE_ACTION_TOOLS.has(button.dataset.chartTool);
        });
        const longTool = drawingToolbar.querySelector('[data-chart-tool="long-position"]');
        const shortTool = drawingToolbar.querySelector('[data-chart-tool="short-position"]');
        if (longTool) {
            longTool.textContent = 'Mua';
            longTool.title = 'Tạo vị thế mua với điểm vào, SL và TP';
        }
        if (shortTool) {
            shortTool.textContent = 'Bán';
            shortTool.title = 'Tạo vị thế bán với điểm vào, SL và TP';
        }
        drawingToolbar.querySelectorAll('.drawing-separator').forEach((separator) => {
            separator.hidden = true;
        });

        const circleTool = drawingToolbar.querySelector('[data-chart-tool="ellipse"]');
        if (circleTool) {
            circleTool.textContent = 'Khoanh';
            circleTool.title = 'Khoanh vùng trên biểu đồ để gửi ngữ cảnh cho AI';
            circleTool.setAttribute('aria-label', 'Khoanh vùng gửi AI');
        }
        const watchlistTitle = $('.watchlist-head strong');
        const drawingTitle = $('.drawing-list-head:not(.backtest-side-head) strong');
        if (watchlistTitle) watchlistTitle.textContent = 'Cặp giao dịch';
        if (drawingTitle) drawingTitle.textContent = 'Vùng giá';

        ['chartAlertList', 'chartDataWindow', 'chartNewsList'].forEach((id) => {
            const panel = document.getElementById(id);
            if (!panel) return;
            panel.previousElementSibling?.remove();
            panel.remove();
        });

        ['.chart-bottom-bar', '.paper-trade-panel'].forEach((selector) => {
            const element = $(selector);
            if (element) element.hidden = true;
        });

        state.chartView.chartType = 'candles';
        state.chartView.favoritesVisible = false;
        const propertiesButton = $('#drawingPropertiesBtn');
        if (propertiesButton) propertiesButton.textContent = 'Sửa';
    }

    function activateTab(tab) {
        $$('.nav-tab').forEach((button) => button.classList.toggle('is-active', button.dataset.tab === tab));
        $$('.tab-panel').forEach((panel) => panel.classList.toggle('is-active', panel.id === `tab-${tab}`));
        state.activeTab = tab;
        document.body.dataset.activeTab = tab;
        const paPanel = $('#tab-pa-training');
        const chartPanel = $('#marketChartPanel');
        const fullscreenButton = $('#chartFullscreenBtn');
        if (tab === 'pa-training') {
            chartPanel?.classList.remove('is-fullscreen');
            setChartButtonLabel(fullscreenButton, paPanel?.classList.contains('is-pa-fullscreen') ? 'Thu gọn' : 'Phóng to');
            const symbol = state.paTrainingSymbol || paTrainingSymbols()[0];
            const timeframe = state.paTrainingChartTimeframe || state.paTrainingTimeframe || 'H4';
            const file = symbol ? findBestMarketFileForSymbol(symbol, timeframe) : null;
            const loaded = state.activeMarketData;
            const remembered = symbol ? savedPaWorkspace(symbol, state.paTrainingTimeframe) : null;
            const shouldResume = !!remembered?.selectedCaseId && !state.paTrainingQueueLoaded && !state.paTrainingResumeLoading;
            const alreadyLoaded = file && loaded && sameSymbol(loaded.symbol, file.symbol)
                && String(loaded.timeframe || '').toUpperCase() === String(file.timeframe || '').toUpperCase();
            if (shouldResume) {
                resumePaTrainingWorkspace(remembered).catch((error) => showToast(error.message));
            } else if (file && !alreadyLoaded && !state.paTrainingChartLoading && !state.paTrainingResumeLoading) {
                loadPaTrainingChartForSymbol(file.symbol, file.timeframe, { silent: true }).catch((error) => showToast(error.message));
            } else if (alreadyLoaded) {
                restorePaTrainingChartViewport(loaded, savedPaWorkspace(symbol, state.paTrainingTimeframe));
            }
        } else if (tab === 'pa-journal') {
            renderPaJournal();
            loadPaJournalMemory({ silent: true }).catch((error) => showToast(error.message));
        } else {
            paPanel?.classList.remove('is-pa-fullscreen');
            setChartButtonLabel(fullscreenButton, chartPanel?.classList.contains('is-fullscreen') ? 'Thu gọn' : 'Phóng to');
        }
        mountSharedMarketChart(tab);
        $('#pageTitle').textContent = titles[tab][0];
        $('#pageSubtitle').textContent = titles[tab][1];
        revealNavSectionForTab(tab);
        closeHelpTooltip();
        if (tab === 'trainer' && activeCase()) {
            setTimeout(() => drawCaseChart(readEditedCase()), 0);
        }
        if ((tab === 'mt5' || tab === 'pa-training') && state.activeMarketData) {
            setTimeout(() => drawMarketChart(state.activeMarketData), 0);
        }
        if (tab === 'backtest-v2' && !state.backtestV2Loaded && !state.backtestV2Loading) {
            loadBacktestV2Results().catch((error) => showToast(error.message));
        }
        if (tab === 'strategy-versions' && !state.strategyV2Loaded && !state.strategyV2Loading) {
            loadStrategyV2Versions().catch((error) => showToast(error.message));
        }
        if (tab === 'kpi-profiles' && !state.kpiProfilesLoaded && !state.kpiProfilesLoading) {
            loadKPIProfiles().catch((error) => showToast(error.message));
        }
        if (tab === 'experiments' && !state.experimentsLoaded && !state.experimentLoading) {
            loadExperiments().catch((error) => showToast(error.message));
        }
        if (tab === 'ai-orchestrator') {
            if (!state.aiJobsLoaded && !state.aiJobsLoading) {
                loadAIJobs().catch((error) => showToast(error.message));
            }
            if (!state.strategyV2Loaded && !state.strategyV2Loading) {
                loadStrategyV2Versions().then(renderAIJobs).catch(() => {});
            }
            if (!state.experimentsLoaded && !state.experimentLoading) {
                loadExperiments().then(renderAIJobs).catch(() => {});
            }
        }
        if (tab === 'prompt-ai' && !state.promptTemplatesLoaded && !state.promptTemplatesLoading) {
            loadPromptTemplates().catch((error) => showToast(error.message));
        }
    }

    function activateTabFromHash() {
        const tab = location.hash.replace('#', '');
        if (tab && titles[tab]) {
            activateTab(tab);
        }
    }

    function createAccount() {
        const copy = structuredClone(selectedAccount() || {});
        copy.id = `account_${Date.now()}`;
        copy.name = 'Tài khoản mới';
        state.accounts.push(copy);
        state.selectedAccountId = copy.id;
        renderAll();
    }

    async function saveSelectedAccount() {
        const updated = readAccountForm();
        const index = state.accounts.findIndex((account) => account.id === state.selectedAccountId);
        if (index >= 0) {
            state.accounts[index] = updated;
        } else {
            state.accounts.push(updated);
        }
        state.selectedAccountId = updated.id;
        const data = await api('save_accounts', { accounts: state.accounts });
        state.accounts = data.accounts;
        showToast('Đã lưu hồ sơ tài khoản');
        renderAll();
    }

    async function saveStrategy() {
        state.strategy = readStrategyForm();
        const data = await api('save_strategy', { strategy: state.strategy });
        state.strategy = data.strategy;
        showToast('Đã lưu chiến lược');
        renderAll();
    }

    async function createStrategyV1() {
        const data = await api('create_strategy_v1', {});
        state.strategyVersions = data.strategy_versions || [];
        state.strategyTree = data.strategy_tree || null;
        state.selectedStrategyVersionId = data.version?.id || state.selectedStrategyVersionId;
        showToast('Đã tạo chiến lược v1 từ chiến lược hiện tại');
        renderAll();
    }

    async function refreshStrategyVersions() {
        const data = await api('strategy_versions', {});
        state.strategyVersions = data.strategy_versions || [];
        state.strategyTree = data.strategy_tree || null;
        renderAll();
    }

    async function updateStrategyVersionStatus(versionId, status) {
        const data = await api('strategy_version_status', {
            strategyVersionId: versionId,
            status,
            approvalNotes: `Người dùng đặt trạng thái thành ${translateStatus(status)}`,
        });
        state.strategyVersions = data.strategy_versions || [];
        state.strategyTree = data.strategy_tree || null;
        showToast(`Đã cập nhật phiên bản: ${translateStatus(status)}`);
        renderAll();
    }

    async function loadStrategyV2Versions(selectedId = '') {
        state.strategyV2Loading = true;
        state.strategyV2Error = '';
        renderStrategyV2Manager();
        try {
            const data = await api('strategy_v2_list', { limit: 100 });
            applyStrategyV2Response(data, selectedId || state.selectedStrategyV2Id);
        } catch (error) {
            state.strategyV2Error = error.message;
            throw error;
        } finally {
            state.strategyV2Loading = false;
            renderStrategyV2Manager();
        }
    }

    async function createStrategyV2() {
        const payload = readStrategyV2Payload();
        const data = await api('strategy_v2_create', payload);
        applyStrategyV2Response(data, data.strategy_version?.id || data.version?.id || '');
        showToast('Đã tạo phiên bản chiến lược V2');
        renderStrategyV2Manager();
    }

    async function updateStrategyV2() {
        const selected = selectedStrategyV2Version();
        if (!selected) throw new Error('Chưa chọn phiên bản V2.');
        const payload = readStrategyV2Payload();
        payload.id = selected.id;
        const data = await api('strategy_v2_update', payload);
        applyStrategyV2Response(data, selected.id);
        showToast('Đã cập nhật phiên bản chiến lược V2');
        renderStrategyV2Manager();
    }

    async function cloneStrategyV2() {
        const selected = selectedStrategyV2Version();
        if (!selected) throw new Error('Chưa chọn phiên bản V2.');
        const payload = readStrategyV2Payload();
        payload.sourceVersionId = selected.id;
        if ((payload.version_code || '') === (selected.version_code || selected.versionCode || '')) {
            delete payload.version_code;
        }
        if ((payload.name || '') === (selected.name || '')) {
            payload.name = `${selected.name || 'Strategy version'} clone`;
        }
        const data = await api('strategy_v2_clone', payload);
        applyStrategyV2Response(data, data.strategy_version?.id || data.version?.id || '');
        showToast('Đã clone phiên bản chiến lược V2');
        renderStrategyV2Manager();
    }

    async function approveStrategyV2(production) {
        const selected = selectedStrategyV2Version();
        if (!selected) throw new Error('Chưa chọn phiên bản V2.');
        const action = production ? 'strategy_v2_production' : 'strategy_v2_approve';
        const data = await api(action, {
            id: selected.id,
            approvedBy: 'user',
            production,
        });
        applyStrategyV2Response(data, selected.id);
        showToast(production ? 'Đã đưa phiên bản lên Production' : 'Đã phê duyệt phiên bản');
        renderStrategyV2Manager();
    }

    async function archiveStrategyV2() {
        const selected = selectedStrategyV2Version();
        if (!selected) throw new Error('Chưa chọn phiên bản V2.');
        const data = await api('strategy_v2_archive', { id: selected.id });
        applyStrategyV2Response(data, selected.id);
        showToast('Đã lưu trữ phiên bản chiến lược V2');
        renderStrategyV2Manager();
    }

    async function openStrategyV2Reference(strategyVersionId) {
        if (!strategyVersionId) return;
        await loadStrategyV2Versions(strategyVersionId);
        if (!findStrategyV2Version(strategyVersionId)) {
            showToast('Không tìm thấy phiên bản chiến lược V2 tương ứng');
            return;
        }
        state.selectedStrategyV2Id = strategyVersionId;
        activateTab('strategy-versions');
        renderStrategyV2Manager();
    }

    function applyStrategyV2Response(data, selectedId = '') {
        state.strategyV2Versions = data.strategy_versions || [];
        state.strategyV2Latest = data.latest_strategy_version || null;
        state.strategyV2Production = data.production_strategy_version || null;
        state.selectedStrategyV2Id = selectedId || data.strategy_version?.id || data.version?.id || state.strategyV2Versions[0]?.id || '';
        state.strategyV2Loaded = true;
        state.strategyV2Error = '';
    }

    function clearStrategyV2Form() {
        $('#strategyV2Name').value = 'Phiên bản chiến lược mới';
        $('#strategyV2Code').value = '';
        $('#strategyV2Parent').value = '';
        $('#strategyV2Status').value = 'Draft';
        $('#strategyV2CreatedBy').value = 'user';
        $('#strategyV2Description').value = '';
        $('#strategyV2RulesJson').value = jsonForTextarea({});
        $('#strategyV2SettingsJson').value = jsonForTextarea({});
        $('#strategyV2PerformanceJson').value = jsonForTextarea({});
    }

    function readStrategyV2Payload() {
        const payload = {
            name: $('#strategyV2Name').value.trim() || 'Strategy version',
            description: $('#strategyV2Description').value.trim(),
            parent_version_id: $('#strategyV2Parent').value || null,
            status: $('#strategyV2Status').value || 'Draft',
            created_by: $('#strategyV2CreatedBy').value || 'user',
            rules_json: parseJsonTextarea('strategyV2RulesJson'),
            settings_json: parseJsonTextarea('strategyV2SettingsJson'),
            performance_json: parseJsonTextarea('strategyV2PerformanceJson'),
        };
        const code = $('#strategyV2Code').value.trim();
        if (code) payload.version_code = code;
        return payload;
    }

    function parseJsonTextarea(id) {
        const value = document.getElementById(id).value.trim();
        if (!value) return {};
        try {
            const parsed = JSON.parse(value);
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                throw new Error('JSON phải là object.');
            }
            return parsed;
        } catch (error) {
            throw new Error(`${document.getElementById(id).previousElementSibling?.textContent || id}: ${error.message}`);
        }
    }

    async function runStrategyV2Backtest() {
        const selected = selectedStrategyV2Version();
        if (!selected) throw new Error('Chưa chọn phiên bản V2.');
        const rules = selected.rules_json || selected.rulesJson || {};
        const symbol = (rules.symbols || []).filter(Boolean)[0] || $('#backtestSymbolSelect')?.value || state.strategy?.symbols?.[0] || '';
        await runServerBacktest({
            strategyVersionId: selected.id,
            strategyVersionSource: 'v2',
            symbol,
        });
    }

    function backtestResultRunId(backtest) {
        const summary = backtest?.summary || {};
        return backtest?.backtestRunId || backtest?.id || summary.backtestRunId || summary.id || '';
    }

    function backtestResultSymbol(backtest) {
        const summary = backtest?.summary || {};
        return String(backtest?.symbol || summary.symbol || $('#backtestSymbolSelect')?.value || '').toUpperCase();
    }

    function backtestResultTimeframe(backtest) {
        const summary = backtest?.summary || {};
        return String(backtest?.timeframe || summary.timeframe || 'M15').toUpperCase();
    }

    function findBacktestChartFile(backtest) {
        const symbol = backtestResultSymbol(backtest);
        if (!symbol) return null;
        const preferred = backtestResultTimeframe(backtest);
        const candidates = [preferred, 'M15', 'H4', 'M30', 'H1', 'M5', 'M1', 'D1']
            .filter(Boolean)
            .filter((value, index, list) => list.indexOf(value) === index);
        for (const timeframe of candidates) {
            const file = findMarketFile(symbol, timeframe);
            if (file) return file;
        }
        return (state.marketFiles || []).find((item) => item.symbol === symbol) || null;
    }

    async function openBacktestResultChart(backtest) {
        if (!backtest) return;
        const runId = backtestResultRunId(backtest);
        const file = findBacktestChartFile(backtest);
        if (!file) {
            const symbol = backtestResultSymbol(backtest) || 'cặp này';
            showToast(`${symbol} chưa có dữ liệu chart. Vào Dữ liệu & MT5 để tải trước.`);
            return;
        }
        activateTab('mt5');
        state.chartView.timeframe = file.timeframe;
        if (runId) state.chartView.activeRunId = runId;
        state.chartView.tradeFilter = 'all';
        state.chartView.overlays.markers = true;
        state.chartView.overlays.slTp = true;
        state.chartView.overlays.positionBands = true;
        state.chartView.overlays.zones = true;
        await loadMarketData(file.file);
        if (runId) state.chartView.activeRunId = runId;
        state.chartView.tradeFilter = 'all';
        const analytics = getChartAnalytics(state.activeMarketData);
        const run = activeBacktestRun(analytics);
        focusBacktestRunTradeRange(run);
        showToast(`Đã mở kết quả backtest trên chart ${file.symbol} ${file.timeframe}`);
    }

    function focusBacktestRunTradeRange(run) {
        const bars = state.activeMarketData?.bars || [];
        if (!bars.length) return;
        const trades = filteredTrades(run?.trades || []);
        if (!trades.length) {
            drawMarketChart(state.activeMarketData);
            return;
        }
        const indexes = trades
            .flatMap((trade) => [Number(trade.entryIndex), Number(trade.exitIndex ?? trade.entryIndex)])
            .filter(Number.isFinite);
        if (!indexes.length) {
            drawMarketChart(state.activeMarketData);
            return;
        }
        const minIndex = Math.max(0, Math.min(...indexes));
        const maxIndex = Math.min(bars.length - 1, Math.max(...indexes));
        const tradeSpan = Math.max(1, maxIndex - minIndex + 1);
        const minBars = Math.min(70, bars.length);
        const span = clamp(Math.ceil(tradeSpan * 1.18), minBars, bars.length);
        const first = clamp(Math.round(minIndex - span * 0.09), 0, Math.max(0, bars.length - span));
        const offsetLimits = chartRightOffsetLimits(bars.length, span);
        state.chartView.barsOnScreen = span;
        state.chartView.rightOffset = clamp(bars.length - first - span, offsetLimits.min, offsetLimits.max);
        state.chartView.priceScale = 1;
        state.chartView.priceOffset = 0;
        state.chartView.selectedTradeId = trades[trades.length - 1]?.id || null;
        drawMarketChart(state.activeMarketData);
    }

    async function runServerBacktest(options = {}) {
        const source = options.strategyVersionSource || 'legacy';
        const versionId = options.strategyVersionId || $('#backtestVersionSelect').value || state.selectedStrategyVersionId;
        const symbol = options.symbol || $('#backtestSymbolSelect').value;
        const split = $('#backtestSplitSelect').value;
        const payload = {
            strategyVersionId: versionId,
            strategy_version_id: versionId,
            strategyVersionSource: source,
            symbol,
            dataSplit: split,
            startDate: split === 'custom' ? $('#backtestStartDate').value : '',
            endDate: split === 'custom' ? $('#backtestEndDate').value : '',
            initialBalance: num($('#backtestInitialBalance').value),
            spreadPoints: $('#backtestSpread').value === '' ? null : num($('#backtestSpread').value),
            feeR: num($('#backtestFeeR').value),
            slippagePoints: num($('#backtestSlippage').value),
            rulesPreset: $('#backtestPresetSelect')?.value || 'balanced',
            debugLevel: num($('#backtestDebugLevelSelect')?.value || 2),
        };
        const data = await api('run_backtest', payload);
        state.lastServerBacktest = data.backtest;
        state.strategyVersions = data.strategy_versions || state.strategyVersions;
        state.backtestRuns = data.backtest_runs || state.backtestRuns;
        if (source === 'v2') {
            state.selectedStrategyV2Id = versionId;
            state.backtestV2FilterVersionId = versionId;
        } else {
            state.selectedStrategyVersionId = versionId;
        }
        state.chartView.activeRunId = data.backtest?.backtestRunId || data.backtest?.id || state.chartView.activeRunId;
        state.chartView.analyticsCache = {};
        showToast('Đã chạy xong kiểm thử chiến lược');
        if (state.backtestV2Loaded || source === 'v2') {
            loadBacktestV2Results(data.backtest?.backtestRunId || data.backtest?.id || '').catch(() => {});
        }
        if (state.experimentsLoaded) {
            loadExperiments(state.selectedExperimentId).catch(() => {});
        }
        await checkBacktestDataQuality();
        renderAll();
        await openBacktestResultChart(data.backtest);
    }

    async function checkBacktestDataQuality() {
        const symbol = $('#backtestSymbolSelect')?.value;
        const version = selectedStrategyVersion();
        const timeframe = version?.rulesJson?.entryTimeframe || 'M15';
        if (!symbol) return;
        try {
            const data = await api('data_quality', { file: `market_data/${symbol}_${timeframe}.json` });
            const q = data.quality;
            $('#backtestDataQuality').innerHTML = `
                <strong>${escapeHtml(translateStatus(q.status || ''))}</strong><br>
                ${escapeHtml(q.symbol)} ${escapeHtml(q.timeframe)} | ${number(q.totalCandles)} nến | ${escapeHtml(q.startDate || '')} → ${escapeHtml(q.endDate || '')}<br>
                Thiếu nến: ${number(q.missingCandles || 0)} | Trùng thời gian: ${number(q.duplicateTimestamps || 0)} | OHLC không hợp lệ: ${number(q.invalidOhlcCount || 0)}
            `;
        } catch (error) {
            $('#backtestDataQuality').textContent = translateDisplayText(error.message);
        }
    }

    async function runStrategyEvolution() {
        const parentId = $('#evolutionParentSelect').value || state.selectedStrategyVersionId;
        const data = await api('tradebrain_evolve', {
            parentStrategyVersionId: parentId,
            symbol: $('#evolutionSymbolSelect').value,
            settings: {
                initialBalance: num($('#evolutionInitialBalance').value),
            },
        });
        state.lastEvolutionResult = data;
        state.strategyVersions = data.strategy_versions || state.strategyVersions;
        state.strategyTree = data.strategy_tree || state.strategyTree;
        state.strategyUpgradeProposals = data.strategy_upgrade_proposals || state.strategyUpgradeProposals;
        state.strategyEvaluations = data.strategy_evaluations || state.strategyEvaluations;
        state.tradebrainMemory = data.tradebrain_memory || state.tradebrainMemory;
        state.aiDecisions = data.ai_decisions || state.aiDecisions;
        state.backtestRuns = data.backtest_runs || state.backtestRuns;
        state.aiUsage = data.aiUsage || state.aiUsage;
        state.selectedStrategyVersionId = data.newVersion?.id || state.selectedStrategyVersionId;
        showToast(data.evaluation?.passed ? 'TradeBrain đã tạo phiên bản mới: đạt yêu cầu' : 'TradeBrain đã tạo phiên bản mới: không đạt yêu cầu');
        renderAll();
    }

    async function importMemory(event) {
        const file = event.target.files[0];
        if (!file) return;
        const text = await file.text();
        const snapshot = JSON.parse(text);
        await api('import_memory', { snapshot });
        showToast('Đã nhập bộ nhớ');
        await bootstrap();
    }

    async function runEvaluation() {
        const data = await api('evaluate_memory', {});
        const evaluation = data.evaluation;
        const mismatches = evaluation.mismatches || [];
        $('#evalResult').innerHTML = `
            <strong>${escapeHtml(evaluation.scorer)}</strong><br>
            Tổng: ${number(evaluation.total)} | Khớp: ${number(evaluation.matched)} | Độ đúng: ${escapeHtml(evaluation.accuracy_pct)}%
            ${
                mismatches.length
                    ? `<div class="review-list" style="margin-top:10px;">${mismatches
                          .map((m) => `<span>${escapeHtml(m.symbol)} ${escapeHtml(m.time)} kỳ vọng ${escapeHtml(displayAiDecision(m.expected))}, thực tế ${escapeHtml(displayAiDecision(m.actual))} (${escapeHtml(m.confidence)})</span>`)
                          .join('')}</div>`
                    : ''
            }
        `;
        showToast('Đã đánh giá xong');
    }

    function fieldHtml(label, id, value, type = 'text') {
        return `
            <label class="field">
                <span>${escapeHtml(label)}</span>
                <input id="${escapeAttr(id)}" type="${escapeAttr(type)}" value="${escapeAttr(value ?? '')}">
            </label>
        `;
    }

    function helperFieldHtml(label, id, value, type = 'text', help = '') {
        return `
            <label class="field">
                <span>${escapeHtml(label)}</span>
                <input id="${escapeAttr(id)}" type="${escapeAttr(type)}" value="${escapeAttr(value ?? '')}">
                <small>${escapeHtml(help)}</small>
            </label>
        `;
    }

    function helperSelectHtml(label, id, value, options, help = '') {
        return `
            <label class="field">
                <span>${escapeHtml(label)}</span>
                <select id="${escapeAttr(id)}">
                    ${options
                        .map((option) => `<option value="${escapeAttr(option)}" ${option === value ? 'selected' : ''}>${escapeHtml(displayOption(option))}</option>`)
                        .join('')}
                </select>
                <small>${escapeHtml(help)}</small>
            </label>
        `;
    }

    function sectionTitleHtml(title, help = '') {
        return `
            <div class="form-section-title">
                <strong>${escapeHtml(title)}</strong>
                ${help ? `<span>${escapeHtml(help)}</span>` : ''}
            </div>
        `;
    }

    function textareaHtml(label, id, value) {
        return `
            <label class="field full">
                <span>${escapeHtml(label)}</span>
                <textarea id="${escapeAttr(id)}" rows="5">${escapeHtml(value ?? '')}</textarea>
            </label>
        `;
    }

    function selectHtml(label, id, value, options) {
        return `
            <label class="field">
                <span>${escapeHtml(label)}</span>
                <select id="${escapeAttr(id)}">
                    ${options
                        .map((option) => `<option value="${escapeAttr(option)}" ${option === value ? 'selected' : ''}>${escapeHtml(displayOption(option))}</option>`)
                        .join('')}
                </select>
            </label>
        `;
    }

    function checkboxHtml(label, id, checked) {
        return `
            <label class="field">
                <span>${escapeHtml(label)}</span>
                <input id="${escapeAttr(id)}" type="checkbox" ${checked ? 'checked' : ''}>
            </label>
        `;
    }

    function setStatus(text) {
        $('#appStatus').textContent = text;
    }

    function showToast(message) {
        const toast = $('#toast');
        toast.textContent = translateDisplayText(message);
        toast.classList.add('is-visible');
        clearTimeout(showToast.timer);
        showToast.timer = setTimeout(() => toast.classList.remove('is-visible'), 2800);
    }

    function money(value) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) return 'Không có';
        return Number(value).toLocaleString('vi-VN', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 2,
        });
    }

    function number(value) {
        return Number(value || 0).toLocaleString('vi-VN');
    }

    function formatDateTime(value) {
        if (!value) return 'chưa có thời gian';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatPercentValue(value) {
        const n = Number(value);
        if (!Number.isFinite(n)) return '0%';
        return `${formatFixed(n, 2)}%`;
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function formatChartTime(value) {
        const date = typeof value === 'number' ? new Date(value * 1000) : new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        if (state.chartView?.timezone && state.chartView.timezone !== 'UTC') {
            if (state.chartView.timezone === 'local') {
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const hour = String(date.getHours()).padStart(2, '0');
                const minute = String(date.getMinutes()).padStart(2, '0');
                return `${day}/${month} ${hour}:${minute}`;
            }
            try {
                return new Intl.DateTimeFormat('vi-VN', {
                    timeZone: state.chartView.timezone,
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).format(date).replace(',', '');
            } catch (error) {
                // Fall back to UTC below.
            }
        }
        const day = String(date.getUTCDate()).padStart(2, '0');
        const month = String(date.getUTCMonth() + 1).padStart(2, '0');
        const hour = String(date.getUTCHours()).padStart(2, '0');
        const minute = String(date.getUTCMinutes()).padStart(2, '0');
        return `${day}/${month} ${hour}:${minute}`;
    }

    function formatNumberValue(value) {
        if (value === null || value === undefined || value === '') return '';
        const n = Number(value);
        return Number.isFinite(n) ? String(value) : String(value);
    }

    function formatFixed(value, digits = 2) {
        const n = Number(value);
        return Number.isFinite(n) ? n.toFixed(digits) : '0';
    }

    function formatSigned(value, digits = 2) {
        const n = Number(value);
        if (!Number.isFinite(n)) return '0';
        return `${n > 0 ? '+' : ''}${n.toFixed(digits)}`;
    }

    function formatDuration(seconds) {
        const n = Math.max(0, Number(seconds) || 0);
        if (n >= 86400) return `${formatFixed(n / 86400, 1)} ngày`;
        if (n >= 3600) return `${formatFixed(n / 3600, 1)} giờ`;
        if (n >= 60) return `${formatFixed(n / 60, 1)} phút`;
        return `${Math.round(n)} giây`;
    }

    function formatPrice(value, symbol) {
        const s = String(symbol || '');
        const digits = s.includes('JPY') ? 3 : 5;
        return Number(value).toFixed(digits);
    }

    function pipSizeForSymbol(symbol) {
        return String(symbol || '').includes('JPY') ? 0.01 : 0.0001;
    }

    function displayDirection(value) {
        const map = {
            buy: 'MUA',
            sell: 'BÁN',
        };
        return map[String(value || '').toLowerCase()] || String(value || '').toUpperCase();
    }

    function displayTradeSide(value) {
        const map = {
            long: 'Mua',
            short: 'Bán',
        };
        return map[String(value || '').toLowerCase()] || String(value || '');
    }

    function displayExitType(value) {
        const map = {
            TP: 'Chạm TP',
            SL: 'Chạm SL',
            BE: 'Hòa vốn',
            TRAIL: 'Dừng kéo theo',
            SIGNAL: 'Đảo tín hiệu',
            TIME: 'Hết thời gian',
        };
        return map[String(value || '').toUpperCase()] || String(value || '');
    }

    function displayAiDecision(value) {
        const map = {
            enter: 'VÀO LỆNH',
            skip: 'BỎ QUA',
        };
        return map[String(value || '').toLowerCase()] || String(value || '');
    }

    function displayReviewDecision(value) {
        const map = {
            approved: 'Đã duyệt',
            rejected: 'Đã bỏ',
            edited: 'Đã sửa',
        };
        return map[String(value || '').toLowerCase()] || String(value || '');
    }

    function displayAccountType(value) {
        const map = {
            prop_firm: 'Quỹ',
            personal: 'Cá nhân',
        };
        return map[String(value || '')] || String(value || '');
    }

    function displayRiskModel(value) {
        const map = {
            prop_buffer_capped: 'Biên an toàn quỹ có giới hạn',
            equity_percent: '% vốn thực',
            fixed_usd: 'USD cố định',
        };
        return map[String(value || '')] || String(value || '');
    }

    function displaySetup(value) {
        const map = {
            h4_strong_zone_direct_limit: 'Đặt lệnh chờ trực tiếp tại vùng H4 mạnh',
            h4_trend_m15_sideway_break: 'Xu hướng H4 + M15 đi ngang rồi phá cấu trúc',
            h4_trend_m15_qml: 'Xu hướng H4 + QML M15',
        };
        return map[String(value || '')] || String(value || '');
    }

    function displayZone(value) {
        const map = {
            supply: 'VÙNG CANH SELL',
            demand: 'VÙNG CANH BUY',
        };
        return map[String(value || '').toLowerCase()] || String(value || '').toUpperCase();
    }

    function displayExitRetestLevel(value) {
        const map = {
            broken_m15_key_level: 'Vùng key M15 vừa bị phá',
        };
        return map[String(value || '')] || String(value || '');
    }

    function displayTrend(value) {
        const map = {
            bullish: 'Tăng',
            bearish: 'Giảm',
            sideway: 'Đi ngang',
            unclear: 'Không rõ',
        };
        return map[String(value || '').toLowerCase()] || String(value || '');
    }

    function displayStructure(value) {
        const map = {
            reaction_pending: 'Chờ phản ứng',
            sideway_break_up: 'Đi ngang rồi phá lên',
            sideway_break_down: 'Đi ngang rồi phá xuống',
            qml_after_key_break: 'QML sau khi phá vùng giá quan trọng',
            broke_key_high: 'Phá đỉnh quan trọng',
            broke_key_low: 'Phá đáy quan trọng',
            broke_pullback_key_low: 'Phá đáy quan trọng của nhịp hồi',
            broke_pullback_key_high: 'Phá đỉnh quan trọng của nhịp hồi',
            none_yet: 'Chưa có',
        };
        return map[String(value || '').toLowerCase()] || String(value || '');
    }

    function caseFieldOptions(key, currentValue) {
        const options = {
            setup_type: ['h4_strong_zone_direct_limit', 'h4_trend_m15_sideway_break', 'h4_trend_m15_qml'],
            direction: ['buy', 'sell'],
            h4_trend: ['bullish', 'bearish', 'sideway', 'unclear'],
            h4_zone_type: ['supply', 'demand'],
            m15_structure: ['reaction_pending', 'sideway_break_up', 'sideway_break_down', 'qml_after_key_break'],
        }[key] || [];
        if (currentValue && !options.includes(currentValue)) {
            return [...options, currentValue];
        }
        return options;
    }

    function displayCaseValue(key, value) {
        if (key === 'setup_type') return displaySetup(value);
        if (key === 'direction') return displayDirection(value);
        if (key === 'h4_trend') return displayTrend(value);
        if (key === 'h4_zone_type') return displayZone(value);
        if (key === 'm15_structure') return displayStructure(value);
        return String(value || '');
    }

    function displayOption(value) {
        return displayAccountType(value) !== value
            ? displayAccountType(value)
            : displayRiskModel(value) !== value
              ? displayRiskModel(value)
              : displayExitRetestLevel(value) !== value
                ? displayExitRetestLevel(value)
                : value;
    }

    function num(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

    function intNum(value) {
        return Math.round(num(value));
    }

    function nullableNum(value) {
        if (value === '' || value === null || value === undefined) return null;
        return num(value);
    }

    function slug(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') || `id_${Date.now()}`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function escapeAttr(value) {
        return escapeHtml(value);
    }

    function debounce(fn, wait) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
    }

    function syncPaWorkspaceUi(data = state.activeMarketData) {
        if (!paWorkspaceUi) return;
        const bars = data?.bars || [];
        const replay = state.chartView.replay;
        const bar = replay.enabled ? bars[clamp(replay.index || 0, 0, Math.max(0, bars.length - 1))] : bars[bars.length - 1];
        paWorkspaceUi.sync({
            symbol: data?.symbol || state.paTrainingSymbol,
            timeframe: data?.timeframe || state.paTrainingChartTimeframe || 'H4',
            price: bar ? formatPrice(bar.close, data.symbol) : '',
            hasData: bars.length > 0,
            hasCase: !!(paTrainingSelectedTeachingCase() || paTrainingSelectedZone()),
            loading: state.paTrainingLoading || state.paTrainingCaseLoading,
            tool: state.chartView.activeTool,
            zone: state.chartView.pendingZoneType,
            playing: replay.playing,
            indicators: state.chartView.indicators,
        });
    }

    function runPaWorkspaceAction(action) {
        if (action === 'skip-case') { skipPaTrainingCurrentCase(); return; }
        if (action === 'saved-cases') { paCaseManager?.history(); return; }
        if (action === 'edit-case') { paCaseManager?.edit(); return; }
        if (action === 'nearby-zones') {
            paNearbyZonesVisible = !paNearbyZonesVisible;
            if ($('#paNearbyZonesNote')) $('#paNearbyZonesNote').hidden = !paNearbyZonesVisible;
            document.querySelectorAll('[data-pa-command="nearby-zones"]').forEach(button => button.setAttribute('aria-pressed', String(paNearbyZonesVisible)));
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
            return;
        }
        if (action === 'trade-markers') {
            state.chartView.teachingLevelMarkersVisible = state.chartView.teachingLevelMarkersVisible === false;
            if (state.activeMarketData) drawMarketChart(state.activeMarketData);
            syncPaWorkspaceUi();
            return;
        }
        if (action.startsWith('tool:')) {
            setChartTool(action.slice(5));
            syncPaWorkspaceUi();
            return;
        }
        if (action.startsWith('zone:')) {
            setChartZone(action.slice(5));
            syncPaWorkspaceUi();
            return;
        }
        const controls = {
            refresh: 'paTrainingRefreshBtn', 'new-case': 'paTrainingLoadZonesBtn',
            'start-cases': 'paTrainingStartCasesBtn', previous: 'chartReplayBackBtn',
            play: 'chartReplayPlayBtn', next: 'chartReplayStepBtn', replay: 'chartReplayPanelToggleBtn',
            undo: 'chartUndoBtn', redo: 'chartRedoBtn', delete: 'chartDeleteSelectedBtn',
            clear: 'chartClearDrawingsBtn', fullscreen: 'chartFullscreenBtn',
            first: 'chartJumpStartBtn', last: 'chartJumpEndBtn', fit: 'chartFitBtn',
            'fit-x': 'chartFitHorizontalBtn', 'fit-y': 'chartFitVerticalBtn',
            theme: 'chartThemeBtn', screenshot: 'chartScreenshotBtn',
        };
        if (controls[action]) document.getElementById(controls[action])?.click();
        syncPaWorkspaceUi();
    }

    function applyPaCaseCorrection(changes, note) {
        const trade = paTrainingSelectedTrade();
        if (!trade) return;
        trade.originalTrade ||= paTrainingTradeSnapshot(trade);
        Object.assign(trade, changes, {corrected: true, correctionNote: note, resultStatus: 'not_recalculated'});
        trade.zone = {...(trade.zone || {}), low: changes.zoneLow, high: changes.zoneHigh};
        trade.rr = Math.abs(changes.targetPrice - changes.entryPrice) / Math.abs(changes.entryPrice - changes.initialStop);
        trade.rrTarget2 = changes.targetPrice2 ? Math.abs(changes.targetPrice2 - changes.entryPrice) / Math.abs(changes.entryPrice - changes.initialStop) : null;
        trade.riskR = Math.abs(changes.entryPrice - changes.initialStop);
        for (const key of ['netR', 'pnlUsd', 'exitPrice', 'exitTime', 'exitIndex', 'exitType', 'mfeR', 'maeR', 'holdingBars']) trade[key] = null;
        state.paTrainingSessionDrafts[paTrainingSessionKey(trade, paTrainingSelectedZone())] = {decision: 'adjusted_case', label: 'Đã điều chỉnh setup', note, updatedAt: new Date().toISOString()};
        renderPaTraining();
        renderPaTrainingSessionStrip();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    function resetPaTrainingCaseLevels() {
        const trade = paTrainingSelectedTeachingCase();
        const original = trade?.originalTrade;
        if (!trade || !original || !paTrainingCaseHasRestorableChanges(trade)) return;
        const fields = [
            'entryPrice', 'initialStop', 'targetPrice', 'targetPrice2', 'rr', 'rrTarget2', 'riskR',
            'zoneLow', 'zoneHigh', 'exitIndex', 'exitCandleNumber', 'exitTime', 'exitPrice', 'exitType',
            'resultStatus', 'sameBarAmbiguous', 'netR', 'pnlUsd', 'mfeR', 'maeR', 'holdingBars',
            'corrected', 'correctionNote',
        ];
        fields.forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(original, key)) trade[key] = original[key];
        });
        const zoneLow = original.zoneLow ?? original.zone?.low;
        const zoneHigh = original.zoneHigh ?? original.zone?.high;
        if (trade.zone && zoneLow != null && zoneHigh != null) {
            trade.zone = {...trade.zone, low: zoneLow, high: zoneHigh};
        }
        const sessionKey = paTrainingSessionKey(trade, paTrainingSelectedZone());
        if (state.paTrainingSessionDrafts[sessionKey]?.decision === 'adjusted_case') {
            delete state.paTrainingSessionDrafts[sessionKey];
        }
        renderPaTraining();
        renderPaTrainingSessionStrip();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
        showToast('Đã khôi phục Entry, SL, TP và kết quả ban đầu của ca.');
    }

    async function openPaSavedCase(review) {
        const snapshot = review.snapshot?.trade ? review.snapshot : review.snapshot?.summary;
        if (!snapshot?.trade) throw new Error('Ca này không có snapshot lệnh.');
        const trade = {...snapshot.trade, zone: snapshot.zone || snapshot.trade.zone, originalTrade: snapshot.originalTrade || snapshot.trade, savedReviewId: review.id, savedReviewUpdatedAt: review.updatedAt || review.createdAt, isTeachingCase: true, review};
        state.paTrainingSymbol = trade.symbol;
        const index = state.paTrainingTradeCases.findIndex(item => item.id === trade.id);
        if (index < 0) state.paTrainingTradeCases.unshift(trade); else state.paTrainingTradeCases[index] = trade;
        state.paTrainingSelectedCaseId = trade.id;
        state.paTrainingSelectedZoneId = trade.zoneId || trade.zone?.id || '';
        state.paTrainingZoneFilter = 'all';
        await focusPaTrainingSelectedCase(snapshot.chart?.timeframe || snapshot.session?.chart?.timeframe || trade.timeframe || 'H4');
        const chart = snapshot.session?.chart;
        if (chart && state.activeMarketData && sameSymbol(trade.symbol, state.activeMarketData.symbol)) {
            state.chartView.indicators = {...state.chartView.indicators, ...chart.indicators};
            // Restore saved drawings without discarding other drawings made since the save.
            const key = chartDrawingKey(state.activeMarketData);
            const existing = getChartDrawings(state.activeMarketData);
            const merged = new Map(existing.map(item => [item.id, item]));
            (chart.drawings || []).forEach(item => merged.set(item.id, JSON.parse(JSON.stringify(item))));
            state.chartView.drawings[key] = [...merged.values()];
        }
        state.paTrainingSessionDrafts[paTrainingSessionKey(trade, trade.zone)] = {decision: review.decision || 'session_note', label: review.label, note: review.note};
        renderPaTraining();
        renderPaTrainingSessionStrip();
        if (state.activeMarketData) drawMarketChart(state.activeMarketData);
    }

    preparePriceActionChartUi();
    bindEvents();
    paCaseManager = window.PaCaseManager?.init({
        api, toast: showToast, trade: paTrainingSelectedTrade, apply: applyPaCaseCorrection, open: openPaSavedCase,
        selectedZone: () => {
            const drawing = selectedDrawingObjects().find(item => item.type === 'rect');
            if (!drawing) return null;
            const points = drawing.points || [];
            const prices = points.map(point => Number(point.price)).filter(Number.isFinite);
            return prices.length >= 2 ? {low: Math.min(...prices), high: Math.max(...prices)} : null;
        },
        memory: memory => { state.paTrainingMemory = normalizePaTrainingMemory(memory); renderPaTrainingStats(); renderPaTrainingRecentFeedback(); },
        updated: review => {
            const current = paTrainingSelectedTeachingCase();
            if (current?.savedReviewId === review?.id) {
                const snapshot = review.snapshot;
                Object.assign(current, snapshot.trade, {zone: snapshot.zone, originalTrade: snapshot.originalTrade, savedReviewUpdatedAt: review.updatedAt, review});
                state.paTrainingSessionDrafts[paTrainingSessionKey(current, current.zone)] = {decision: review.decision, label: review.label, note: review.note};
                renderPaTraining();
                if (state.activeMarketData) drawMarketChart(state.activeMarketData);
            }
        },
        changed: (review, action) => {
            state.paTrainingTradeCases.forEach(trade => {
                if (trade.review?.id !== review.id && trade.savedReviewId !== review.id) return;
                trade.review = action === 'delete' ? null : review;
                if (action === 'delete') { delete trade.savedReviewId; delete trade.savedReviewUpdatedAt; }
            });
            renderPaTraining();
        },
    });
    paWorkspaceUi = window.PaWorkspace?.init({
        action: runPaWorkspaceAction,
        symbol: (symbol) => changePaTrainingSymbol(symbol),
        timeframe: (timeframe) => changePaWorkspaceTimeframe(timeframe).catch((error) => showToast(error.message)),
        resize: () => {
            if (state.activeTab === 'pa-training' && state.activeMarketData) drawMarketChart(state.activeMarketData);
        },
    });
    bootstrap().catch((error) => {
        setStatus('Lỗi');
        showToast(error.message);
    });
})();























