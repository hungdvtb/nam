(() => {
    'use strict';
    function init(adapter) {
        const dialog = document.createElement('dialog');
        dialog.className = 'pa-case-dialog';
        dialog.id = 'paCaseDialog';
        dialog.setAttribute('aria-labelledby', 'paCaseDialogTitle');
        document.body.appendChild(dialog);
        let saved = [];
        let trash = false;
        let activeReview = null;
        let editingTrade = null;
        let busy = false;
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const snapshot = review => review?.snapshot?.trade ? review.snapshot : review?.snapshot?.summary || {};
        const fields = [['zoneLow','Đáy vùng vào lệnh'],['zoneHigh','Đỉnh vùng vào lệnh'],['entryPrice','Entry'],['initialStop','SL'],['targetPrice','TP1'],['targetPrice2','TP2 (không bắt buộc)']];
        function shell(title, body) {
            dialog.innerHTML = `<header><h2 id="paCaseDialogTitle">${title}</h2><button type="button" data-case-close aria-label="Đóng" title="Đóng">×</button></header><div class="pa-case-body">${body}<p class="pa-case-error" role="alert"></p></div>`;
            dialog.querySelector('[data-case-close]').onclick = () => dialog.close();
            if (!dialog.open) dialog.showModal();
        }
        function error(message) { dialog.querySelector('.pa-case-error').textContent = message; }
        function editor(trade, review = null) {
            if (!trade) { adapter.toast('Chọn một ca trade trong hàng đợi trước khi chỉnh setup.'); return; }
            activeReview = review;
            editingTrade = trade;
            const data = review ? snapshot(review) : {};
            const original = data.originalTrade || trade.originalTrade || trade;
            shell(review ? 'Sửa ca đã lưu' : 'Điều chỉnh setup', `
                <p><strong>${esc(trade.symbol)} · ${trade.direction === 'short' ? 'Bán' : 'Mua'}</strong> — chỉ sửa phần bạn muốn, giữ nguyên các giá còn lại.</p>
                <p class="pa-case-hint">Không cần đánh dấu đúng/sai. Bản đề xuất gốc được giữ lại để so sánh. Kết quả lời/lỗ cũ không áp dụng cho setup đã sửa.</p>
                <form id="paCaseEditForm"><div class="pa-case-fields">${fields.map(([key,label]) => `<label>${label}<input name="${key}" type="number" step="any" min="0.00000001" ${key === 'targetPrice2' ? '' : 'required'} value="${esc(trade[key] ?? (key === 'zoneLow' ? trade.zone?.low : key === 'zoneHigh' ? trade.zone?.high : '') ?? '')}"><small>Gốc: ${esc(original[key] ?? '—')}</small></label>`).join('')}</div>
                <label class="pa-case-note">Lý do điều chỉnh<textarea name="note" rows="3" maxlength="3000" placeholder="Ví dụ: vùng đúng nhưng entry cần đợi giá về thấp hơn...">${esc(review?.note || trade.correctionNote || '')}</textarea></label>
                <div class="pa-case-buttons">${review ? '' : '<button type="button" data-use-zone>Lấy vùng chữ nhật đang chọn trên chart</button>'}<button class="button" type="submit">${review ? 'Cập nhật ca đã lưu' : 'Áp dụng lên chart'}</button></div></form>`);
            const form = dialog.querySelector('form');
            dialog.querySelector('[data-use-zone]')?.addEventListener('click', () => {
                const zone = adapter.selectedZone();
                if (!zone) { error('Đóng bảng, chọn vùng chữ nhật đã vẽ trên chart rồi mở lại nút Sửa.'); return; }
                form.elements.zoneLow.value = zone.low;
                form.elements.zoneHigh.value = zone.high;
                error('');
            });
            form.addEventListener('submit', async event => {
                event.preventDefault();
                if (busy) return;
                const changes = Object.fromEntries(fields.map(([key]) => [key, form.elements[key].value === '' ? null : Number(form.elements[key].value)]));
                const note = form.elements.note.value.trim();
                const validation = validate(trade, changes);
                if (validation) { error(validation); return; }
                busy = true;
                form.querySelector('[type="submit"]').disabled = true;
                try {
                    if (review) {
                        const result = await adapter.api('pa_training_saved_case_update', {id:review.id, expectedUpdatedAt:review.updatedAt || review.createdAt, changes, note});
                        saved = result.saved_cases;
                        adapter.memory(result.pa_training_memory);
                        adapter.updated(saved.find(r => r.id === review.id));
                        adapter.toast('Đã cập nhật ca đã lưu.');
                        renderList();
                    } else {
                        adapter.apply(changes, note);
                        dialog.close();
                        adapter.toast('Đã chỉnh setup trên chart. Bấm Lưu ca để lưu bản chỉnh sửa.');
                    }
                } catch (e) { error(e.message); }
                finally { busy = false; if (form.isConnected) form.querySelector('[type="submit"]').disabled = false; }
            });
        }
        function validate(trade, v) {
            if (fields.some(([k]) => k !== 'targetPrice2' && (!Number.isFinite(v[k]) || v[k] <= 0))) return 'Giá phải là số dương hợp lệ.';
            if (v.targetPrice2 !== null && (!Number.isFinite(v.targetPrice2) || v.targetPrice2 <= 0)) return 'TP2 phải là số dương.';
            if (v.zoneLow >= v.zoneHigh) return 'Đáy vùng phải thấp hơn đỉnh vùng.';
            const long = trade.direction !== 'short';
            if (long ? !(v.initialStop < v.entryPrice && v.entryPrice < v.targetPrice) : !(v.targetPrice < v.entryPrice && v.entryPrice < v.initialStop)) return long ? 'Lệnh mua cần SL < Entry < TP1.' : 'Lệnh bán cần TP1 < Entry < SL.';
            if (v.targetPrice2 !== null && (long ? v.targetPrice2 <= v.targetPrice : v.targetPrice2 >= v.targetPrice)) return 'TP2 phải xa entry hơn TP1 theo hướng lệnh.';
            return '';
        }
        function detail(review) {
            const snap = snapshot(review);
            const trade = snap.trade || {};
            shell('Chi tiết ca đã lưu', `<p><strong>${esc(review.symbol)} · ${esc(review.label)}</strong></p><p>${esc(review.note)}</p>
                <dl class="pa-case-details">${fields.map(([key,label]) => `<div><dt>${label}</dt><dd>${esc(trade[key] ?? '—')}</dd></div>`).join('')}</dl>
                <p class="pa-case-hint">Lưu lúc ${esc(review.createdAt)}${review.updatedAt ? ' · Sửa lúc '+esc(review.updatedAt) : ''}</p>
                <div class="pa-case-buttons"><button data-back>Quay lại</button><button data-edit ${!snap.trade || review.deletedAt ? 'disabled' : ''}>Sửa setup</button><button data-open ${!snap.trade || review.deletedAt ? 'disabled' : ''}>Mở lại trên chart</button></div>
                <details><summary>Hội thoại đã lưu (${snap.session?.chatMessages?.length || 0})</summary>${(snap.session?.chatMessages || []).map(m=>`<p><strong>${m.role==='user'?'Bạn':'AI'}</strong>: ${esc(m.content)}</p>`).join('')}</details>`);
            dialog.querySelector('[data-back]').onclick = renderList;
            dialog.querySelector('[data-edit]').onclick = () => editor(trade, review);
            dialog.querySelector('[data-open]').onclick = async () => {
                try { await adapter.open(review); dialog.close(); } catch(e) { error(e.message); }
            };
        }
        function renderList() {
            const items = saved.filter(r => !!r.deletedAt === trash);
            shell('Ca đã lưu', `<div class="pa-case-buttons"><button data-tab-active ${!trash?'class="is-active"':''}>Đã lưu</button><button data-tab-trash ${trash?'class="is-active"':''}>Thùng rác</button><button data-refresh>Tải lại</button></div><p class="pa-case-hint">Có thể sửa setup và ghi chú, mở lại chart hoặc xóa ca. Ca xóa được giữ trong thùng rác và không dùng làm bộ nhớ đang hoạt động.</p><div class="pa-saved-list">${items.length ? items.map(r=>`<article><div><strong>${esc(r.symbol)} · ${esc(r.label || 'Ca trao đổi')}</strong><small>${esc(r.createdAt)}</small><p>${esc(r.note || 'Chưa có ghi chú')}</p></div><div class="pa-case-buttons"><button data-detail="${esc(r.id)}">Xem</button>${trash?`<button data-restore="${esc(r.id)}">Khôi phục</button>`:`<button data-edit="${esc(r.id)}" ${snapshot(r).trade?'':'disabled'}>Sửa</button><button data-delete="${esc(r.id)}">Xóa</button>`}</div></article>`).join('') : '<p>Chưa có ca nào.</p>'}</div>`);
            dialog.querySelector('[data-tab-active]').onclick = () => {trash=false;renderList();};
            dialog.querySelector('[data-tab-trash]').onclick = () => {trash=true;renderList();};
            dialog.querySelector('[data-refresh]').onclick = history;
            dialog.querySelectorAll('[data-detail]').forEach(b=>b.onclick=()=>detail(saved.find(r=>r.id===b.dataset.detail)));
            dialog.querySelectorAll('[data-edit]').forEach(b=>b.onclick=()=>{const r=saved.find(r=>r.id===b.dataset.edit);editor(snapshot(r).trade,r);});
            ['delete','restore'].forEach(action=>dialog.querySelectorAll(`[data-${action}]`).forEach(b=>b.onclick=async()=>{
                if (busy) return;
                const review = saved.find(r=>r.id===b.dataset[action]);
                busy=true; b.disabled=true;
                try {
                    const result = await adapter.api(`pa_training_saved_case_${action}`, {id:review.id, expectedUpdatedAt:review.updatedAt || review.createdAt});
                    saved=result.saved_cases; adapter.memory(result.pa_training_memory); adapter.changed(review,action); renderList();
                } catch(e) {error(e.message);b.disabled=false;} finally {busy=false;}
            }));
        }
        async function history() {
            shell('Ca đã lưu', '<p>Đang tải...</p>');
            try { const result=await adapter.api('pa_training_saved_cases',{}); saved=result.saved_cases; adapter.memory(result.pa_training_memory); renderList(); }
            catch(e) {error(e.message);}
        }
        return { edit: () => editor(adapter.trade()), history };
    }
    window.PaCaseManager = { init };
})();
