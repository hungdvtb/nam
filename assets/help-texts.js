(function () {
    window.AI_TRADE_HELP = {
        fields: {
            backtestVersionSelect: {
                title: 'Phiên bản chiến lược',
                body: 'Chọn bộ luật giao dịch muốn kiểm thử. Lấy từ Sổ chiến lược/Strategy Version. Nên chọn version đã được kiểm tra xác thực, ví dụ v1 hoặc version calibrated. Không chọn version thử nghiệm nếu chưa hiểu rõ.',
            },
            backtestSymbolSelect: {
                title: 'Cặp tiền',
                body: 'Cặp Forex muốn backtest, ví dụ EURUSD. Dữ liệu lấy từ MT5 hoặc kho dữ liệu nến đã tải. Nên bắt đầu với 1 cặp quen thuộc để dễ kiểm tra chart.',
            },
            backtestSplitSelect: {
                title: 'Phần dữ liệu',
                body: 'Chọn đoạn dữ liệu dùng để kiểm thử. Dữ liệu huấn luyện dùng để học/chỉnh luật. Dữ liệu xác thực dùng để kiểm tra luật sau khi chỉnh. Dữ liệu kiểm tra cuối dùng để đánh giá cuối cùng, không nên chỉnh theo nó. Khoảng tùy chọn dùng khi muốn tự chọn ngày.',
            },
            backtestPresetSelect: {
                title: 'Bộ thiết lập',
                body: 'Mức độ lọc tín hiệu. Nghiêm ngặt = ít lệnh, lọc kỹ. Cân bằng = dùng để kiểm thử chính. Nới lỏng để kiểm tra = tạo nhiều mẫu để debug, không dùng để trade thật.',
            },
            backtestDebugLevelSelect: {
                title: 'Mức thông tin kiểm tra',
                body: 'Mức log/chi tiết khi kiểm thử. Mức thấp chạy nhanh và gọn. Mức cao cho nhiều lý do skip để debug. Khi đang hiệu chỉnh nên chọn mức có mẫu ứng viên để xem bot bỏ qua vì sao.',
            },
            backtestStartDate: {
                title: 'Ngày bắt đầu tùy chọn',
                body: 'Ngày bắt đầu backtest nếu chọn khoảng tùy chọn. Lấy theo dữ liệu nến đã có. Để trống nếu muốn dùng đoạn dữ liệu mặc định.',
            },
            backtestEndDate: {
                title: 'Ngày kết thúc tùy chọn',
                body: 'Ngày kết thúc backtest nếu chọn khoảng tùy chọn. Để trống nếu muốn dùng đoạn dữ liệu mặc định.',
            },
            backtestInitialBalance: {
                title: 'Số dư ban đầu',
                body: 'Vốn giả lập dùng cho backtest. Không phải tiền thật. Nên để 10000 để dễ so sánh các lần test.',
            },
            backtestSpread: {
                title: 'Ghi đè spread',
                body: 'Spread tính bằng point. Nếu để trống, hệ thống dùng spread từ dữ liệu gốc nếu có. Chỉ nhập khi muốn test trong điều kiện spread cố định.',
            },
            backtestFeeR: {
                title: 'Phí giao dịch (R)',
                body: 'Chi phí giao dịch quy đổi theo R. Dùng để mô phỏng phí/slippage. Nên để mặc định nếu chưa chắc.',
            },
            backtestSlippage: {
                title: 'Trượt giá (point)',
                body: 'Mức trượt giá giả lập khi vào/thoát lệnh. Giúp backtest thực tế hơn. Nên để thấp khi test bình thường, tăng lên khi muốn kiểm tra kịch bản xấu.',
            },
            kpiWinRateMinInput: {
                title: 'Tỷ lệ thắng tối thiểu (%)',
                body: 'Tỷ lệ phần trăm lệnh thắng trong toàn bộ kết quả backtest. Đây không phải RR, mà là xác suất thắng của chiến lược.',
                items: [
                    'Công thức: số lệnh thắng / tổng số lệnh x 100.',
                    'Ví dụ: 60 lệnh thắng trên 100 lệnh = 60%.',
                    'Nếu đặt 50%, backtest phải có tỷ lệ thắng từ 50% trở lên mới đạt.',
                ],
            },
            kpiAverageRRMinInput: {
                title: 'RR trung bình tối thiểu',
                body: 'RR trung bình cho biết mỗi lệnh thắng/thua quy đổi theo đơn vị R. Đây là tỷ lệ lời/lỗ trung bình của các lệnh, không phải tổng lợi nhuận của cả hệ thống.',
                items: [
                    'Công thức gần đúng: tổng R kết quả / số lệnh, hoặc trung bình RR thực tế của các lệnh đã đóng.',
                    'Ví dụ: rủi ro 1R, mục tiêu lời 2R thì lệnh thắng đạt khoảng +2R.',
                    'Nếu đặt 2, hệ thống ưu tiên chiến lược có mức lời trung bình quanh 2R trở lên.',
                ],
            },
            kpiProfitFactorMinInput: {
                title: 'Tổng lời / tổng lỗ tối thiểu',
                body: 'Đây là Profit Factor. Chỉ số này đo toàn bộ backtest lời khỏe hay yếu, khác với RR của từng lệnh.',
                items: [
                    'Công thức: tổng lợi nhuận của các lệnh thắng / tổng thua lỗ tuyệt đối của các lệnh thua.',
                    'Ví dụ: tổng lời 2,000 USD, tổng lỗ 1,000 USD thì chỉ số = 2.',
                    'Nếu đặt 1.5, tổng lời phải lớn hơn tổng lỗ ít nhất 1.5 lần mới đạt.',
                ],
            },
            kpiMaxDrawdownMaxInput: {
                title: 'Sụt giảm vốn tối đa (%)',
                body: 'Mức vốn bị tụt sâu nhất từ đỉnh vốn xuống đáy vốn trong quá trình backtest. Chỉ số này dùng để kiểm soát rủi ro chịu đựng.',
                items: [
                    'Công thức: (đỉnh vốn - đáy vốn sau đỉnh) / đỉnh vốn x 100.',
                    'Ví dụ: vốn lên 10,000 rồi tụt xuống 9,000 thì drawdown = 10%.',
                    'Nếu đặt 10%, backtest có lúc sụt quá 10% thì không đạt.',
                ],
            },
            kpiTotalTradesMinInput: {
                title: 'Số lệnh tối thiểu',
                body: 'Số lệnh tối thiểu để kết quả backtest đủ mẫu và bớt may rủi. Quá ít lệnh thì winrate/RR đẹp cũng chưa đáng tin.',
                items: [
                    'Công thức: đếm tổng số lệnh đã đóng trong backtest.',
                    'Ví dụ: đặt 300 nghĩa là backtest phải có ít nhất 300 lệnh.',
                    'Nếu chỉ có 20-30 lệnh, kết quả dễ bị nhiễu và chưa nên dùng để thi quỹ.',
                ],
            },
            autoResearchModeSelect: {
                title: 'Chế độ chạy',
                body: 'Chọn cách AI nội bộ bắt đầu nghiên cứu chiến lược. Hiện tại đây là bộ sinh luật + backtest + chấm KPI trong app, chưa phải OpenAI/Ollama.',
                items: [
                    'Tự sinh từ số 0: phù hợp khi bạn chưa có chiến lược rõ ràng; hệ thống tự tạo nhiều bộ luật mẫu rồi test.',
                    'Tối ưu từ chiến lược có sẵn: phù hợp khi bạn đã có một phương pháp và muốn hệ thống sửa dần.',
                    'Cả hai chế độ chỉ backtest và lưu version nghiên cứu, không đặt lệnh MT5.',
                ],
            },
            autoResearchAiProviderSelect: {
                title: 'Nguồn AI phân tích',
                body: 'Chọn bộ não dùng để định hướng research. AI nội bộ không tốn tiền API. OpenAI API dùng key đã cấu hình và bị giới hạn bởi ngân sách ngày.',
                items: [
                    'AI nội bộ: hệ thống tự sinh rule theo template và diagnostics, phù hợp để chạy nhiều vòng rẻ và nhanh.',
                    'OpenAI API: dùng model OpenAI để chọn hướng nghiên cứu từ danh sách an toàn, nhưng backtest/KPI vẫn do app tính.',
                    'Nếu OpenAI chưa cấu hình hoặc hết ngân sách, nút chạy sẽ bị khóa.',
                ],
            },
            autoResearchSymbolSelect: {
                title: 'Cặp tiền',
                body: 'Chọn một hoặc nhiều cặp để AI nội bộ nghiên cứu. Khi chọn nhiều cặp, hệ thống chạy từng cặp riêng rồi lưu/tổng hợp kết quả, không trộn nến của nhiều cặp vào một backtest.',
                items: [
                    'Nên chạy 1 cặp trước để kiểm tra logic và tốc độ.',
                    'Sau đó có thể chọn nhiều cặp để tìm cặp nào hợp với họ chiến lược đó.',
                    'Tick từng cặp cần chạy, hoặc dùng nút Chọn tất cả/Bỏ chọn để thao tác nhanh.',
                ],
            },
            autoResearchVersionSelect: {
                title: 'Chiến lược gốc',
                body: 'Version làm nền khi chọn chế độ tối ưu từ chiến lược có sẵn. Ở chế độ tự sinh từ số 0, ô này bị khóa vì AI không cần chiến lược gốc.',
            },
            autoResearchMaxRounds: {
                title: 'Số vòng tối đa / mỗi cặp',
                body: 'Số bộ luật hoặc biến thể tối đa mà AI nội bộ được phép thử cho từng cặp tiền trong một lần chạy.',
                items: [
                    'Cách tính: số cặp đã chọn x số vòng tối đa = số lượt backtest tối đa có thể chạy.',
                    'Ví dụ chọn 5 cặp và đặt 12 vòng thì tối đa có thể chạy 60 lượt backtest.',
                    'Số vòng càng cao càng có cơ hội tìm bản tốt hơn, nhưng thời gian chạy cũng lâu hơn.',
                ],
            },
            autoResearchMaxSeconds: {
                title: 'Giới hạn giây / mỗi cặp',
                body: 'Thời gian tối đa hệ thống được phép chạy research cho từng cặp tiền. Hết thời gian thì dừng cặp đó và giữ kết quả tốt nhất đã tìm được.',
                items: [
                    'Cách tính: số cặp đã chọn x giới hạn giây mỗi cặp = thời gian tối đa gần đúng.',
                    'Ví dụ chọn 3 cặp và đặt 300 giây thì tổng thời gian tối đa khoảng 900 giây.',
                    'Đây là phanh an toàn để app không chạy quá lâu khi dữ liệu dài hoặc số vòng lớn.',
                ],
            },
            autoResearchWinRateMin: {
                title: 'Tỷ lệ thắng tối thiểu (%)',
                body: 'KPI để AI tự tối ưu. AI chỉ xem một vòng là đạt nếu tỷ lệ thắng của backtest đạt ngưỡng này.',
                items: [
                    'Công thức: số lệnh thắng / tổng số lệnh x 100.',
                    'Ví dụ: 150 lệnh thắng trên 300 lệnh = 50%.',
                    'Chỉ số này cần xem cùng RR, vì winrate cao nhưng RR quá thấp vẫn có thể không tốt.',
                ],
            },
            autoResearchAverageRrMin: {
                title: 'RR trung bình tối thiểu',
                body: 'KPI để AI tự tối ưu mức lời/lỗ trung bình theo R. Đây là chỉ số riêng, không trùng với tổng lời / tổng lỗ.',
                items: [
                    'Công thức gần đúng: trung bình R của các lệnh hoặc mức RR thực tế theo kết quả backtest.',
                    'Ví dụ: đặt 2 nghĩa là AI phải tìm luật có RR trung bình khoảng 1:2 trở lên.',
                    'Nên xem cùng tỷ lệ thắng để tránh chiến lược RR đẹp nhưng thắng quá ít.',
                ],
            },
            autoResearchProfitFactorMin: {
                title: 'Tổng lời / tổng lỗ tối thiểu',
                body: 'KPI để AI kiểm tra hiệu quả tổng thể của cả backtest. Đây là Profit Factor, khác với RR từng lệnh.',
                items: [
                    'Công thức: tổng lợi nhuận lệnh thắng / tổng thua lỗ tuyệt đối của lệnh thua.',
                    'Ví dụ: tổng lời 3,000 USD, tổng lỗ 1,500 USD thì chỉ số = 2.',
                    'Nếu đặt 1.5, AI chỉ coi đạt khi tổng lời lớn hơn tổng lỗ ít nhất 1.5 lần.',
                ],
            },
            autoResearchMaxDrawdownMax: {
                title: 'Sụt giảm vốn tối đa (%)',
                body: 'KPI giới hạn độ tụt vốn trong quá trình AI tự backtest. Dùng để tránh chiến lược lời cao nhưng chịu drawdown quá nặng.',
                items: [
                    'Công thức: (đỉnh vốn - đáy vốn sau đỉnh) / đỉnh vốn x 100.',
                    'Ví dụ: vốn từ đỉnh 10,000 tụt xuống 9,200 thì drawdown = 8%.',
                    'Nếu đặt 10%, vòng backtest nào tụt vốn quá 10% sẽ bị đánh rớt.',
                ],
            },
            autoResearchTotalTradesMin: {
                title: 'Số lệnh tối thiểu',
                body: 'KPI yêu cầu số mẫu tối thiểu khi AI tự tối ưu. Giúp tránh việc AI chọn một luật chỉ thắng đẹp vì quá ít lệnh.',
                items: [
                    'Công thức: tổng số lệnh đã đóng trong backtest.',
                    'Ví dụ: đặt 300 nghĩa là AI phải tạo được ít nhất 300 lệnh trong dữ liệu đã test.',
                    'Số lệnh càng ít thì độ tin cậy càng thấp.',
                ],
            },
            openAiEnabledInput: {
                title: 'Bật OpenAI API',
                body: 'Cho phép app gọi OpenAI khi bạn chủ động chọn OpenAI API trong các màn AI. Tắt mục này thì key vẫn có thể lưu nhưng provider bị khóa.',
            },
            openAiApiKeyInput: {
                title: 'OpenAI API key',
                body: 'Key dùng để app gọi OpenAI API. Key được lưu trong .env.local trên máy bạn và không được trả lại nguyên văn ra giao diện.',
                items: [
                    'Để trống khi lưu nếu muốn giữ key hiện tại.',
                    'Không paste API key vào chat hoặc commit vào code.',
                    'Nếu nghi key bị lộ, hãy revoke key trên OpenAI Platform và tạo key mới.',
                ],
            },
            openAiModelInput: {
                title: 'Model mặc định',
                body: 'Model OpenAI app sẽ dùng khi gọi API. Nên bắt đầu bằng model rẻ để test nhiều vòng, sau đó mới tăng model mạnh hơn khi cần review sâu.',
            },
            openAiDailyBudgetInput: {
                title: 'Ngân sách tối đa / ngày',
                body: 'Giới hạn chi phí OpenAI ước tính trong một ngày. Mặc định là 1 USD/ngày. App sẽ chặn trước mỗi lần gọi nếu dự toán lần gọi đó có thể vượt phần ngân sách còn lại.',
                items: [
                    'Chi phí được ước tính từ input token và output token của từng lần gọi.',
                    'Backtest nội bộ không tốn API; chỉ các lần gọi OpenAI mới bị tính vào ngân sách này.',
                    'Bảng giá có thể thay đổi theo OpenAI; app dùng giá bảo thủ và cho phép cấu hình override bằng biến môi trường nếu cần.',
                    'Bạn có thể tăng/giảm số này theo nhu cầu.',
                ],
            },
            openAiMaxTokensInput: {
                title: 'Giới hạn output token',
                body: 'Số token tối đa OpenAI được phép trả về trong một lần gọi. Giới hạn thấp giúp kiểm soát chi phí và tránh câu trả lời quá dài.',
            },
            openAiMaxCallsInput: {
                title: 'Số lần gọi tối đa / ngày',
                body: 'Hàng rào phụ để tránh app gọi API quá nhiều lần trong ngày, kể cả khi từng lần gọi có vẻ rẻ.',
                items: [
                    'Đặt 100 nghĩa là tối đa 100 lần gọi OpenAI/ngày.',
                    'Đặt 0 nghĩa là không giới hạn số lần gọi, nhưng vẫn bị chặn bởi ngân sách USD/ngày.',
                    'Nên giữ số này thấp khi đang thử nghiệm auto research.',
                ],
            },
            openAiAdminKeyInput: {
                title: 'OpenAI Admin API key',
                body: 'Key cấp organization dùng để đọc Costs API chính thức của OpenAI. Key này có quyền cao hơn key gọi model nên phải giữ kín.',
                items: [
                    'Tạo tại OpenAI Platform → Organization → Admin keys.',
                    'Chỉ Organization Owner mới tạo được admin key.',
                    'App lưu key trong .env.local và không hiện lại nguyên văn trên giao diện.',
                ],
            },
            calibrationVersionSelect: {
                title: 'Phiên bản chiến lược',
                body: 'Version luật dùng để tạo hàng chờ mẫu cần chấm. Nên dùng version hiện tại đang muốn dạy bot.',
            },
            calibrationSymbolSelect: {
                title: 'Cặp tiền',
                body: 'Cặp tiền lấy mẫu để chấm tay. Nên chọn 1 cặp trước, ví dụ EURUSD, để bot học đúng cách bạn nhìn chart.',
            },
            calibrationPresetSelect: {
                title: 'Bộ thiết lập',
                body: 'Preset dùng để tìm mẫu. Nên dùng Nới lỏng để kiểm tra khi cần nhiều mẫu chấm. Không dùng kết quả này để đánh giá hiệu suất thật.',
            },
            calibrationSplitSelect: {
                title: 'Phần dữ liệu',
                body: 'Nguồn dữ liệu để lấy mẫu hiệu chỉnh. Nên dùng dữ liệu huấn luyện để dạy/chỉnh bot, không dùng dữ liệu kiểm tra cuối.',
            },
            calibrationLimitInput: {
                title: 'Số lượng mẫu',
                body: 'Số mẫu muốn tải vào hàng chờ để chấm. Nên bắt đầu 30-50 mẫu cho mỗi lỗi như ZONE_ALREADY_BROKEN, NO_M15_CONFIRMATION, WEAK_H4_ZONE.',
            },
            calibrationQueueTypeSelect: {
                title: 'Loại hàng chờ',
                body: 'Nhóm lỗi hoặc nhóm mẫu muốn lấy ra để chấm tay. Dữ liệu lấy từ kết quả backtest debug. Nên chọn từng nhóm lỗi một để bot học rõ bạn đang sửa lỗi gì.',
            },
            evolutionParentSelect: {
                title: 'Chiến lược gốc',
                body: 'Version làm nền để TradeBrain đề xuất version mới. Dữ liệu lấy từ Sổ chiến lược. Nên chọn version đã có backtest rõ ràng để so sánh được trước và sau.',
            },
            evolutionSymbolSelect: {
                title: 'Cặp tiền',
                body: 'Cặp dùng để chạy kiểm thử khi tạo đề xuất nâng cấp. Dữ liệu lấy từ kho nến đã tải. Nên bắt đầu với EURUSD hoặc cặp bạn đã chấm nhiều mẫu.',
            },
            evolutionInitialBalance: {
                title: 'Số dư ban đầu',
                body: 'Vốn giả lập dùng khi kiểm tra đề xuất nâng cấp. Không phải tiền thật. Nên giữ 10000 để so sánh công bằng với các lần backtest khác.',
            },
            tradebrainMemoryTypeFilter: {
                title: 'Loại bài học',
                body: 'Bộ lọc bài học TradeBrain theo nhóm nội dung. Dữ liệu lấy từ bộ nhớ TradeBrain. Nên xem cảnh báo rủi ro và bài học kiểm thử trước khi tin một version mới.',
            },
            tradebrainMemoryVersionFilter: {
                title: 'Phiên bản liên quan',
                body: 'Lọc bài học theo version chiến lược. Dữ liệu lấy từ Sổ chiến lược và bộ nhớ TradeBrain. Nên chọn đúng version đang kiểm tra để tránh đọc nhầm bài học của bản khác.',
            },
            mt5TerminalPath: {
                title: 'Đường dẫn terminal64.exe',
                body: 'Đường dẫn tới MT5 nếu ứng dụng không tự thấy MT5 đang mở. Dữ liệu này do bạn nhập và chỉ dùng để kết nối/lấy nến. Nên để trống nếu MT5 đã mở ổn.',
            },
            mt5Symbols: {
                title: 'Cặp cần lấy dữ liệu',
                body: 'Danh sách cặp Forex cần tải nến từ MT5. Nên bắt đầu với EURUSD trước, sau đó thêm từng cặp để dễ kiểm tra dữ liệu và biểu đồ.',
            },
            mt5Bars: {
                title: 'Số nến M15 mục tiêu',
                body: 'Số nến M15 muốn tải từ MT5. H4 sẽ được quy đổi tương ứng. Nên dùng mặc định nếu cần dữ liệu dài, giảm xuống khi chỉ muốn kiểm tra nhanh.',
            },
            chartSymbolSelect: {
                title: 'Mã đang xem',
                body: 'Cặp tiền đang mở trên biểu đồ. Dữ liệu lấy từ thư mục data/market_data sau khi tải MT5. Nên xem đúng cặp vừa backtest để đối chiếu lệnh và vùng giá.',
            },
            riskEquity: {
                title: 'Vốn thực hiện tại',
                body: 'Số vốn hiện tại dùng để xem trước rủi ro. Dữ liệu do bạn nhập để mô phỏng. Nên nhập gần đúng vốn thật của tài khoản demo/quỹ để biết bot được phép rủi ro bao nhiêu.',
            },
            riskDayBalance: {
                title: 'Số dư đầu ngày',
                body: 'Số dư lúc bắt đầu ngày giao dịch. Dữ liệu do bạn nhập. Hệ thống dùng để tính ngưỡng dừng ngày nên cần nhập sát thực tế.',
            },
            riskDayEquity: {
                title: 'Vốn thực đầu ngày',
                body: 'Equity lúc bắt đầu ngày giao dịch. Dữ liệu do bạn nhập. Nên dùng số từ tài khoản/MT5 để mô phỏng giới hạn rủi ro trong ngày chính xác hơn.',
            },
            acc_name: {
                title: 'Tên hồ sơ',
                body: 'Tên dễ nhớ cho cấu hình tài khoản. Dữ liệu lưu trong hồ sơ tài khoản. Nên đặt theo broker/quỹ để không nhầm khi kiểm tra risk.',
            },
            acc_id: {
                title: 'Mã tài khoản',
                body: 'Mã định danh nội bộ của hồ sơ. Dữ liệu lưu trong file tài khoản. Nên giữ ngắn, không dấu, để app tham chiếu ổn định.',
            },
            acc_account_type: {
                title: 'Loại tài khoản',
                body: 'Cho biết tài khoản cá nhân hay tài khoản quỹ. Dữ liệu do bạn chọn. Nên chọn đúng loại vì cách tính giới hạn lỗ và biên an toàn có thể khác nhau.',
            },
            acc_broker: {
                title: 'Sàn giao dịch',
                body: 'Tên broker của tài khoản. Dữ liệu do bạn nhập. Nên ghi rõ để sau này biết dữ liệu spread/điều kiện giao dịch đến từ đâu.',
            },
            acc_initial_balance: {
                title: 'Vốn ban đầu',
                body: 'Vốn gốc dùng làm mốc tính rủi ro. Dữ liệu lưu trong hồ sơ tài khoản. Nên nhập đúng số vốn quỹ hoặc vốn demo để kết quả risk preview có ý nghĩa.',
            },
            acc_currency: {
                title: 'Tiền tệ',
                body: 'Đơn vị tiền của tài khoản, thường là USD. Dữ liệu do bạn nhập. Nên giữ đúng đơn vị tài khoản để số tiền rủi ro không bị hiểu sai.',
            },
            acc_risk_model: {
                title: 'Cách tính rủi ro',
                body: 'Công thức tính số tiền được phép rủi ro mỗi lệnh. Dữ liệu do bạn chọn. Tài khoản quỹ nên dùng biên an toàn quỹ; tài khoản cá nhân có thể dùng % vốn thực.',
            },
            acc_max_loss_pct: {
                title: 'Mức lỗ tối đa %',
                body: 'Ngưỡng lỗ tối đa của tài khoản. Dữ liệu lấy từ luật quỹ hoặc do bạn tự đặt. Nên nhập đúng luật quỹ để bot không vượt vùng nguy hiểm.',
            },
            acc_daily_loss_hard_pct: {
                title: 'Giới hạn lỗ trong ngày %',
                body: 'Mức lỗ ngày không được vượt. Dữ liệu lấy từ luật quỹ hoặc kế hoạch cá nhân. Nên đặt thấp hơn ngưỡng cấm để còn biên an toàn.',
            },
            acc_daily_stop_pct: {
                title: 'Bot dừng ngày %',
                body: 'Ngưỡng bot tự dừng trong ngày. Dữ liệu do bạn đặt. Nên thấp hơn giới hạn lỗ ngày để hệ thống dừng trước khi tài khoản gặp rủi ro lớn.',
            },
            acc_risk_base_cap_pct: {
                title: 'Giới hạn vốn tính rủi ro %',
                body: 'Mức trần vốn được dùng để tính rủi ro. Dữ liệu do bạn đặt. Nên dùng khi muốn bot không tăng lot quá mạnh dù tài khoản đang lời.',
            },
            acc_risk_per_trade_pct: {
                title: 'Rủi ro mỗi lệnh %',
                body: 'Phần trăm vốn rủi ro cho một lệnh. Dữ liệu do bạn đặt. Nên để nhỏ khi đang kiểm thử, ví dụ 0.25%-1%, để chịu được chuỗi thua.',
            },
            acc_survival_reserve_pct: {
                title: 'Đệm max loss không được dùng %',
                body: 'Phần trăm của mức lỗ tối đa mà bot luôn giữ lại làm vùng cấm. Ví dụ max loss 10K, đặt 20% nghĩa là 2K cuối cùng không được dùng để tính lot; khi equity tiến gần vùng này bot sẽ giảm vol hoặc dừng.',
            },
            acc_daily_reserve_pct: {
                title: 'Đệm dừng ngày không được dùng %',
                body: 'Phần trăm của biên dừng ngày mà bot không dùng để mở lệnh mới. Đệm này giúp dừng sớm trước khi chạm daily stop, nhất là khi có spread, slippage hoặc lệnh đóng muộn.',
            },
            acc_max_open_trades: {
                title: 'Lệnh mở tối đa',
                body: 'Số lệnh được mở cùng lúc. Dữ liệu do bạn đặt. Nên giới hạn thấp để tránh nhiều lệnh cùng thua khi thị trường đảo chiều.',
            },
            acc_max_same_currency_exposure: {
                title: 'Tối đa cùng đồng tiền',
                body: 'Giới hạn số lệnh có cùng một đồng tiền liên quan, ví dụ nhiều cặp EUR. Dữ liệu do bạn đặt. Nên bật giới hạn để tránh rủi ro dồn vào một đồng tiền.',
            },
            acc_max_losing_trades_per_day: {
                title: 'Lệnh thua tối đa/ngày',
                body: 'Số lệnh thua trong ngày trước khi dừng. Dữ liệu do bạn đặt. Nên dùng để tránh cố gỡ khi thị trường không hợp chiến lược.',
            },
            acc_stop_after_consecutive_sl: {
                title: 'Dừng sau chuỗi SL',
                body: 'Số lệnh chạm SL liên tiếp trước khi bot dừng. Dữ liệu do bạn đặt. Nên để thấp khi đang thử chiến lược mới.',
            },
            acc_allow_overnight_hold: {
                title: 'Cho giữ qua đêm',
                body: 'Cho phép giữ lệnh qua ngày mới hay không. Dữ liệu do bạn chọn. Nên tắt nếu muốn tránh spread giãn hoặc swap qua đêm.',
            },
            acc_allow_weekend_hold: {
                title: 'Cho giữ cuối tuần',
                body: 'Cho phép giữ lệnh qua cuối tuần hay không. Dữ liệu do bạn chọn. Nên tắt khi đang kiểm thử an toàn vì gap đầu tuần có thể lớn.',
            },
            acc_friday_close_hours_before_market_close: {
                title: 'Đóng trước cuối tuần',
                body: 'Số giờ trước khi thị trường đóng cuối tuần để thoát lệnh. Dữ liệu do bạn đặt. Nên chừa vài giờ để tránh thanh khoản mỏng và spread giãn.',
            },
            strat_name: {
                title: 'Tên chiến lược',
                body: 'Tên mô tả bộ luật đang chỉnh. Dữ liệu lưu trong cấu hình chiến lược. Nên đặt rõ để phân biệt với các version sau này.',
            },
            strat_min_confidence: {
                title: 'Ngưỡng điểm AI tối thiểu',
                body: 'Điểm tối thiểu để một mẫu được coi là đủ rõ. Dữ liệu do bạn cấu hình. Nên tăng khi muốn lọc kỹ hơn, giảm khi cần nhiều mẫu để xem lỗi.',
            },
            strat_min_rr: {
                title: 'RR tối thiểu',
                body: 'Tỷ lệ lời/lỗ tối thiểu để nhận lệnh. Dữ liệu do bạn cấu hình. Nên giữ mức hợp lý như 2-3R để lệnh có đủ biên lợi nhuận.',
            },
            strat_h4_tp_candidates: {
                title: 'Số vùng TP H4 cần xét',
                body: 'Số vùng H4 dùng làm ứng viên chốt lời. Dữ liệu lấy từ bộ phát hiện vùng. Nên không chọn quá nhiều để mục tiêu TP vẫn gần và thực tế.',
            },
            strat_symbols: {
                title: 'Cặp tiền',
                body: 'Danh sách cặp chiến lược được phép kiểm thử. Dữ liệu do bạn nhập và cũng dùng cho MT5/watchlist. Nên bắt đầu ít cặp để dễ kiểm soát chất lượng dữ liệu.',
            },
            strat_notes: {
                title: 'Ghi chú',
                body: 'Ghi chú cách dùng chiến lược. Dữ liệu do bạn nhập. Nên ghi lý do đổi luật để sau này so lại không bị quên bối cảnh.',
            },
        },
        sections: {
            calibrationQueue: {
                title: 'Hàng chờ hiệu chỉnh',
                body: 'Danh sách các mẫu bot cần bạn chấm. Bạn xem chart rồi đánh dấu bot đúng/sai để dạy lại luật.',
            },
            calibrationItem: {
                title: 'Mục đang xem',
                body: 'Mẫu hiện tại bạn đang chấm. Xem vùng H4, xác nhận M15 và lý do bot skip trước khi chọn nhãn.',
            },
            calibrationReport: {
                title: 'Báo cáo và gợi ý hiệu chỉnh',
                body: 'Tổng hợp các mẫu đã chấm. Dùng để biết bot hay sai ở đâu, sau đó tạo preset calibrated mới.',
            },
            evolutionPanel: {
                title: 'Tiến hóa chiến lược',
                body: 'Màn này chỉ để TradeBrain phân tích dữ liệu đã có và đề xuất version mới. Chỉ chạy khi đã có đủ mẫu chấm và kết quả kiểm thử rõ ràng.',
            },
            comparePanel: {
                title: 'So sánh phiên bản',
                body: 'Màn này so version cũ và version mới bằng kết quả kiểm thử. Nên nhìn train, validation và final test, không chỉ nhìn một con số lời/lỗ.',
            },
            playbookPanel: {
                title: 'Sổ chiến lược',
                body: 'Nơi xem lại luật và nguyên tắc giao dịch đã khai báo. Dữ liệu lấy từ cấu hình chiến lược, không phải kết quả TradeBrain tự bịa ra.',
            },
            mt5Panel: {
                title: 'Kết nối MT5',
                body: 'Màn này dùng để kiểm tra kết nối và tải dữ liệu nến. Chỉ lấy dữ liệu, không đặt lệnh thật qua MT5.',
            },
            mt5Timeframes: {
                title: 'Khung thời gian',
                body: 'Các timeframe muốn tải từ MT5. Hệ thống thường cần H4 để tìm vùng lớn và M15 để xác nhận điểm vào. Nên giữ H4 và M15 trước, chỉ thêm khung khác khi cần xem biểu đồ phụ.',
            },
            tradebrainMemoryPanel: {
                title: 'Bộ nhớ TradeBrain',
                body: 'Nơi lưu bài học, cảnh báo và nhật ký quyết định. Đây là dữ liệu tham khảo để giải thích vì sao nên hoặc không nên nâng cấp chiến lược.',
            },
            riskPreviewPanel: {
                title: 'Xem trước rủi ro',
                body: 'Màn này mô phỏng giới hạn rủi ro theo hồ sơ tài khoản. Dữ liệu do bạn nhập, không tự đặt lệnh và không trừ tiền thật.',
            },
            accountProfilePanel: {
                title: 'Hồ sơ tài khoản',
                body: 'Nơi cấu hình vốn, giới hạn lỗ và cách tính rủi ro. Nên nhập đúng luật tài khoản/quỹ để backtest và vận hành thử có biên an toàn.',
            },
        },
        terms: {
            strict: {
                title: 'Nghiêm ngặt',
                body: 'Lọc rất kỹ, ít lệnh. Dùng để kiểm tra chiến lược chặt chẽ.',
            },
            balanced: {
                title: 'Cân bằng',
                body: 'Mức mặc định để backtest chính. Không quá chặt, không quá lỏng.',
            },
            'loose-debug': {
                title: 'Nới lỏng để kiểm tra',
                body: 'Nới điều kiện để có nhiều mẫu debug. Không dùng để trade thật.',
            },
            ZONE_ALREADY_BROKEN: {
                title: 'ZONE_ALREADY_BROKEN',
                body: 'Bot cho rằng vùng H4 đã bị phá trước đó nên bỏ qua.',
            },
            NO_M15_CONFIRMATION: {
                title: 'NO_M15_CONFIRMATION',
                body: 'Bot không thấy xác nhận M15 nên bỏ qua.',
            },
            WEAK_H4_ZONE: {
                title: 'WEAK_H4_ZONE',
                body: 'Bot chấm vùng H4 quá yếu nên bỏ qua.',
            },
            DIRTY_SIDEWAY: {
                title: 'DIRTY_SIDEWAY',
                body: 'Thị trường đi ngang nhiễu, khó vào lệnh sạch.',
            },
            BAD_RR: {
                title: 'BAD_RR',
                body: 'Tỷ lệ lời/lỗ không đạt yêu cầu.',
            },
            LIMIT_NOT_FILLED: {
                title: 'LIMIT_NOT_FILLED',
                body: 'Lệnh chờ không khớp.',
            },
            fresh: {
                title: 'fresh',
                body: 'Vùng mới, chưa bị giá chạm lại nhiều.',
            },
            touched: {
                title: 'touched',
                body: 'Vùng đã bị giá chạm.',
            },
            weakened: {
                title: 'weakened',
                body: 'Vùng đã yếu đi.',
            },
            broken: {
                title: 'broken',
                body: 'Vùng đã bị phá.',
            },
            BROKEN_CORRECT: {
                title: 'BROKEN_CORRECT',
                body: 'Bot đánh dấu vùng bị phá là đúng.',
            },
            BROKEN_WRONG: {
                title: 'BROKEN_WRONG',
                body: 'Bot đánh dấu vùng bị phá là sai.',
            },
            STRENGTH_TOO_LOW: {
                title: 'STRENGTH_TOO_LOW',
                body: 'Bot chấm vùng quá yếu.',
            },
            NO_M15_CONFIRM_WRONG: {
                title: 'NO_M15_CONFIRM_WRONG',
                body: 'Có xác nhận M15 nhưng bot không nhận ra.',
            },
        },
        guide: {
            title: 'Hướng dẫn vận hành AI Trade / TradeBrain',
            intro: 'Hiện tại hệ thống chỉ dùng để học, kiểm thử và hiệu chỉnh chiến lược. Chưa dùng để trade live.',
            steps: [
                {
                    title: 'Kết nối MT5 và tải dữ liệu',
                    items: ['Mở màn “Kết nối MT5”.', 'Kiểm tra MT5 đã kết nối chưa.', 'Tải dữ liệu nến cho các cặp cần test.', 'Ưu tiên EURUSD trước để dễ kiểm tra.'],
                },
                {
                    title: 'Chọn chiến lược',
                    items: ['Vào “Sổ chiến lược”.', 'Chọn version chiến lược đang dùng.', 'Version mới chưa được kiểm tra thì không dùng để đánh giá thật.'],
                },
                {
                    title: 'Chạy backtest ban đầu',
                    items: ['Vào “Kiểm thử chiến lược”.', 'Chọn version hiện tại, EURUSD, dữ liệu huấn luyện hoặc xác thực, bộ thiết lập Cân bằng.', 'Bấm “Bắt đầu kiểm thử”.', 'Xem kết quả và lý do các lệnh bị bỏ qua.'],
                },
                {
                    title: 'Khi quá ít lệnh',
                    items: ['Nếu preset Cân bằng ra quá ít lệnh, chuyển sang “Nới lỏng để kiểm tra”.', 'Mục đích chỉ để lấy nhiều mẫu debug.', 'Không dùng kết quả loose-debug để kết luận chiến lược tốt hay xấu.'],
                },
                {
                    title: 'Hiệu chỉnh thủ công',
                    items: ['Vào “Hiệu chỉnh thủ công”.', 'Tải hàng chờ mẫu.', 'Ưu tiên chấm 50 mẫu ZONE_ALREADY_BROKEN, 50 mẫu NO_M15_CONFIRMATION, 30 mẫu WEAK_H4_ZONE.', 'Mỗi mẫu cần xem chart rồi chấm bot đúng hay sai.'],
                },
                {
                    title: 'Tạo báo cáo hiệu chỉnh',
                    items: ['Sau khi chấm đủ mẫu, bấm “Tạo báo cáo”.', 'Xem bot thường sai ở đâu.', 'Không sửa luật theo cảm tính nếu chưa đủ mẫu.'],
                },
                {
                    title: 'Tạo preset đã hiệu chỉnh',
                    items: ['Từ báo cáo, tạo preset mới, ví dụ: balanced-calibrated-v1.', 'Preset này là bản cân bằng đã học từ mẫu bạn chấm.'],
                },
                {
                    title: 'Backtest lại',
                    items: ['Quay lại “Kiểm thử chiến lược”.', 'Chọn preset mới.', 'Chạy lại trên dữ liệu huấn luyện.', 'Sau đó chạy trên dữ liệu xác thực.', 'Cuối cùng mới chạy trên dữ liệu kiểm tra cuối.'],
                },
                {
                    title: 'Cho TradeBrain nâng cấp chiến lược',
                    items: ['Chỉ cho TradeBrain đề xuất version mới khi có đủ mẫu, logic nhận vùng đúng hơn, và backtest qua train/validation/final test.', 'TradeBrain chỉ được phân tích và đề xuất.', 'Không cho TradeBrain tự bịa kết quả backtest.'],
                },
                {
                    title: 'Nguyên tắc an toàn',
                    items: ['Không trade live.', 'Không gọi lệnh MT5 order execution.', 'Không dùng OpenAI API thật nếu chưa cần.', 'Backtest phải do code tính.', 'AI chỉ phân tích, đề xuất, tạo version mới.'],
                },
            ],
            screenNotes: {
                backtest: ['Chọn dữ liệu train để chỉnh luật, validation để kiểm tra sau khi chỉnh, final test để đánh giá cuối.', 'Dùng Cân bằng cho kiểm thử chính.', 'Chỉ dùng Nới lỏng để kiểm tra khi cần nhiều mẫu debug.'],
                calibration: ['Tải mẫu theo từng lỗi một để chấm cho rõ.', 'Đọc lý do bot bỏ qua, xem vùng H4 và xác nhận M15 trước khi chọn nhãn.', 'Tạo báo cáo sau khi đã có đủ mẫu, đừng sửa luật theo một vài ví dụ lẻ.'],
                evolution: ['Chỉ tạo version mới khi đã có đủ mẫu chấm và kết quả backtest rõ.', 'TradeBrain được đề xuất, còn kết quả đạt hay không phải dựa trên kiểm thử thật.', 'Không dùng màn này để hợp thức hóa một version chưa đủ dữ liệu.'],
                compare: ['So version cũ và mới bằng cùng phần dữ liệu.', 'Ưu tiên version có kết quả ổn định qua train, validation và final test.', 'Nếu version mới chỉ thắng ở train nhưng yếu ở validation thì chưa nên tin.'],
                playbook: ['Đây là sổ tay luật đang dùng.', 'Dùng để kiểm tra lại nguyên tắc vào lệnh trước khi chấm mẫu.', 'Không xem đây là bằng chứng chiến lược tốt nếu chưa có backtest.'],
                'tradebrain-memory': ['Đây là nơi lưu bài học và nhật ký quyết định.', 'Bộ nhớ giúp giải thích vì sao TradeBrain đề xuất thay đổi.', 'Không coi bài học là kết quả backtest; kết quả phải do code kiểm thử tính.'],
                mt5: ['Màn này chỉ tải dữ liệu nến từ MT5.', 'Nên tải EURUSD trước rồi kiểm tra biểu đồ.', 'Không có thao tác đặt lệnh thật ở đây.'],
                accounts: ['Nhập đúng vốn và luật rủi ro của tài khoản.', 'Dùng màn này để xem bot được phép rủi ro bao nhiêu.', 'Khi chưa chắc, chọn mức rủi ro mỗi lệnh thấp.'],
                dashboard: ['Dùng để xem nhanh risk preview và trạng thái gần đây.', 'Các số ở đây là mô phỏng theo dữ liệu bạn nhập.', 'Không dùng màn tổng quan để quyết định trade live.'],
            },
        },
    };
})();
