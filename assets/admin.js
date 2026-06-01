document.addEventListener('DOMContentLoaded',function(){
            const dcaSettings = window.dcaTbSettings || {};
            const nonce = dcaSettings.nonce;
            const filterUrl = dcaSettings.filterUrl;
            const notDoneUrl = dcaSettings.notDoneUrl;
            const filterLabel = dcaSettings.filterLabel;
    
            if(!document.querySelector('.dca-toolbar')){
                const bar=document.createElement('div');
                bar.className='dca-toolbar';
                bar.innerHTML='<span class="dca-toolbar-title">SEO:</span><button type="button" class="button" id="dca-copy-selected">Kopieer</button><button type="button" class="button" id="dca-open-empty-bulk">Bulkeditor</button><button type="button" class="button" id="dca-export-selected">Export .txt</button><button type="button" class="button" id="dca-deselect-selected">Deselecteer alles</button><button type="button" class="button button-primary" id="dca-open-import">Import .txt</button><a class="button" href="'+filterUrl+'">'+filterLabel+'</a>';
                document.body.appendChild(bar);
            }
    
            const $=s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));
            const toast=$('#dca-toast'), singleModal=$('#dca-single-modal'), singleOut=$('#dca-single-output'), singleTitle=$('#dca-single-title'), singleStatus=$('#dca-single-status'), singleView=$('#dca-single-view');
            const bulkModal=$('#dca-bulk-modal'), bulkOut=$('#dca-bulk-output'), bulkStatus=$('#dca-bulk-status'), bulkPreview=$('#dca-bulk-preview'), bulkSave=$('#dca-bulk-save');
            const importModal=$('#dca-import-modal'), importFile=$('#dca-import-file'), importStatus=$('#dca-import-status'), importPreviewBox=$('#dca-import-preview-box'), importRun=$('#dca-import-run');
    
            let currentPostId=null, singleFilename='content-sync.txt', bulkFilename='content-sync.txt', importTxt='', importOk=false, cache={}, singleInitial='', bulkInitial='', bulkChecked=false, toastTimer=null;
    
            function showToast(msg){toast.textContent=msg;toast.classList.add('is-active');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.remove('is-active'),3500)}
            function open(m){m.classList.add('is-active')}
            function close(m){m.classList.remove('is-active')}
            function status(el,msg,type){el.textContent=msg||'';el.classList.remove('is-success','is-error');if(type)el.classList.add(type)}
            function dirty(type){return type==='single'&&singleOut.value!==singleInitial||type==='bulk'&&bulkOut.value!==bulkInitial}
            function closeSafe(modal,type){if(dirty(type)&&!confirm('Je hebt wijzigingen die nog niet zijn opgeslagen. Toch sluiten?'))return;close(modal)}
            function ajax(action,data){
                const fd=new FormData();
                fd.append('action',action);
                fd.append('nonce',nonce);
                Object.keys(data||{}).forEach(k=>Array.isArray(data[k])?data[k].forEach(v=>fd.append(k+'[]',v)):fd.append(k,data[k]));
    
                return fetch(ajaxurl,{
                    method:'POST',
                    credentials:'same-origin',
                    headers:{'Accept':'application/json'},
                    body:fd
                }).then(response=>response.text().then(text=>{
                    let parsed=null;
    
                    try{
                        parsed=text?JSON.parse(text):null;
                    }catch(e){
                        const preview=String(text||'').replace(/\s+/g,' ').trim().slice(0,500);
                        return {
                            success:false,
                            data:{
                                message:'Server gaf geen geldige JSON terug. HTTP '+response.status+'. Eerste response: '+(preview||'lege response')
                            }
                        };
                    }
    
                    if(!response.ok){
                        if(parsed&&parsed.data&&parsed.data.message){
                            return parsed;
                        }
    
                        return {
                            success:false,
                            data:{message:'AJAX-verzoek mislukt. HTTP '+response.status+'.'}
                        };
                    }
    
                    return parsed||{
                        success:false,
                        data:{message:'Lege AJAX-response.'}
                    };
                })).catch(error=>({
                    success:false,
                    data:{message:'AJAX-verzoek mislukt: '+(error&&error.message?error.message:String(error))}
                }));
            }
            function copy(text,el){navigator.clipboard.writeText(text).then(()=>status(el,'Gekopieerd.','is-success')).catch(()=>{document.execCommand('copy');status(el,'Gekopieerd.','is-success')})}
            function download(text,name){const b=new Blob([text],{type:'text/plain;charset=utf-8'}),u=URL.createObjectURL(b),a=document.createElement('a');a.href=u;a.download=name||'content-sync.txt';document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(u)}
            function selectedIds(){return $$('tbody th.check-column input[type="checkbox"][name="post[]"]:checked').map(c=>c.value)}
            function esc(v){return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
            function slug(v){return String(v).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')}
    
            function reloadList(msg){showToast((msg||'Opgeslagen')+'. Lijst wordt bijgewerkt...');setTimeout(function(){window.location.href=notDoneUrl||window.location.href},900)}
            function updateSelectionToast(){showToast('Content Syncken: '+selectedIds().length+' geselecteerd')}
            function deselectAll(){
                $$('tbody th.check-column input[type="checkbox"][name="post[]"], #cb-select-all-1, #cb-select-all-2').forEach(c=>{c.checked=false;c.indeterminate=false});
                updateSelectionToast();
            }
    
            document.addEventListener('change',e=>{if(e.target.matches('input[type="checkbox"][name="post[]"], #cb-select-all-1, #cb-select-all-2'))setTimeout(updateSelectionToast,40)});
            document.addEventListener('click',e=>{if(e.target&&e.target.id==='dca-deselect-selected'){e.preventDefault();deselectAll()}});
    
            function preload(){
                const ids=$$('.dca-open-acf-textblock').map(b=>b.dataset.postId).filter(Boolean);
                if(!ids.length)return;
                ajax('dca_preload_acf_textblocks',{post_ids:ids}).then(d=>{if(d&&d.success&&d.data.items)cache=d.data.items}).catch(()=>{});
            }
    
            preload();
    
            function saveBulkDraft(){
                try{
                    if(bulkOut.value.trim()){
                        localStorage.setItem('dca_tb_bulk_draft', bulkOut.value);
                    }
                }catch(e){}
            }
    
            function clearBulkDraft(){
                try{
                    localStorage.removeItem('dca_tb_bulk_draft');
                }catch(e){}
            }
    
            function getBulkDraft(){
                try{
                    return localStorage.getItem('dca_tb_bulk_draft') || '';
                }catch(e){
                    return '';
                }
            }
    
            function renderPreview(box,items){
                let html='<table><thead><tr><th>Bron</th><th>Gekoppelde pagina</th><th>Status</th></tr></thead><tbody>';
                items.forEach(i=>{
                    const cls=i.status==='success'?'dca-ok':(i.status==='partial'?'dca-partial':'dca-error');
                    const target=i.target_title?i.target_title+' (#'+i.target_post_id+')':'Niet gevonden';
                    html+='<tr><td><strong>'+esc(i.source_title)+'</strong><br>ID: '+esc(i.source_id)+'</td><td>'+esc(target)+'</td><td class="'+cls+'">'+esc(i.message)+'</td></tr>';
                });
                box.innerHTML=html+'</tbody></table>';
                box.style.display='block';
            }
    
            $$('.dca-open-acf-textblock').forEach(btn=>btn.addEventListener('click',function(){
                currentPostId=this.dataset.postId;
                status(singleStatus,'','');
                singleFilename='content-sync-'+currentPostId+'.txt';
    
                const fill=d=>{
                    singleTitle.textContent='Content Sync: '+d.title;
                    singleFilename='content-sync-'+slug(d.title)+'.txt';
                    singleOut.value=d.text;
                    singleInitial=d.text;
                    singleView.href=d.view_url||'#';
                    open(singleModal);
                    singleOut.focus();
                    singleOut.select();
                };
    
                if(cache[currentPostId]){fill(cache[currentPostId]);return}
    
                singleTitle.textContent='Content Sync';
                singleOut.value='Tekst wordt opgehaald...';
                singleInitial=singleOut.value;
                open(singleModal);
    
                ajax('dca_get_acf_textblock',{post_id:currentPostId}).then(d=>{
                    if(!d||!d.success){singleOut.value=d&&d.data&&d.data.message?d.data.message:'Er ging iets mis.';return}
                    cache[currentPostId]=d.data;
                    fill(d.data);
                }).catch(()=>singleOut.value='Er ging iets mis.');
            }));
    
            $('#dca-single-save').addEventListener('click',function(){
                if(!currentPostId){status(singleStatus,'Geen pagina geselecteerd.','is-error');return}
                if(!confirm('Weet je zeker dat je dit contentblok wilt opslaan? Er wordt automatisch eerst een back-up gemaakt.'))return;
                this.disabled=true;status(singleStatus,'Back-up maken en opslaan...','');
    
                ajax('dca_save_acf_textblock',{post_id:currentPostId,textblock:singleOut.value}).then(d=>{
                    this.disabled=false;
                    if(!d||!d.success){status(singleStatus,d&&d.data&&d.data.message?d.data.message:'Opslaan mislukt.','is-error');return}
                    singleInitial=singleOut.value;
                    cache[currentPostId]={title:singleTitle.textContent.replace('Content Sync: ',''),text:singleOut.value,view_url:singleView.href};
                    status(singleStatus,d.data.message||'Opgeslagen.','is-success');
                    reloadList('Pagina opgeslagen');
                }).catch(()=>{this.disabled=false;status(singleStatus,'Opslaan mislukt.','is-error')});
            });
    
            $('#dca-single-copy').addEventListener('click',()=>{singleOut.focus();singleOut.select();copy(singleOut.value,singleStatus)});
            $('#dca-single-download').addEventListener('click',()=>{download(singleOut.value,singleFilename);status(singleStatus,'TXT-bestand gedownload.','is-success')});
            $('.dca-close-single').addEventListener('click',()=>closeSafe(singleModal,'single'));
    
            function fetchBulk(){
                const ids=selectedIds();
    
                if(!ids.length){
                    showToast('Content Syncken: 0 geselecteerd');
                    alert('Selecteer eerst één of meerdere items.');
                    return Promise.reject();
                }
    
                if(ids.length > 50 && !confirm('Je hebt '+ids.length+' pagina’s geselecteerd. Dit kan zwaar zijn voor de server. Toch doorgaan?')){
                    return Promise.reject();
                }
    
                return ajax('dca_bulk_get_acf_textblocks',{post_ids:ids});
            }
    
            function resetBulkCheck(){bulkChecked=false;bulkSave.disabled=true;bulkPreview.style.display='none';bulkPreview.innerHTML=''}
            bulkOut.addEventListener('input',function(){resetBulkCheck();saveBulkDraft()});
    
            $('#dca-open-empty-bulk').addEventListener('click',function(){
                const draft = getBulkDraft();
    
                bulkOut.value='';
                bulkInitial='';
                bulkFilename='content-sync-handmatig.txt';
                resetBulkCheck();
    
                if(draft && confirm('Er staat nog een lokaal concept van de bulkeditor. Wil je dit herstellen?')){
                    bulkOut.value=draft;
                    bulkInitial=draft;
                }
    
                status(bulkStatus,'Plak hier je bulktekst en klik daarna op “Controleer bulktekst”.','');
                open(bulkModal);
                bulkOut.focus();
                showToast('Bulkeditor geopend. Plak je tekst en controleer vóór opslaan.');
            });
    
            $('#dca-copy-selected').addEventListener('click',function(){
                bulkOut.value='Contentblokken worden opgehaald...';
                bulkInitial=bulkOut.value;
                resetBulkCheck();
                status(bulkStatus,'','');
                open(bulkModal);
    
                fetchBulk().then(d=>{
                    if(!d||!d.success){bulkOut.value=d&&d.data&&d.data.message?d.data.message:'Ophalen mislukt.';status(bulkStatus,'Ophalen mislukt.','is-error');return}
                    bulkOut.value=d.data.text;
                    bulkInitial=d.data.text;
                    bulkFilename=d.data.filename||bulkFilename;
                    saveBulkDraft();
                    bulkOut.focus();
                    bulkOut.select();
                    copy(bulkOut.value,bulkStatus);
                }).catch(()=>close(bulkModal));
            });
    
            $('#dca-export-selected').addEventListener('click',()=>fetchBulk().then(d=>{
                if(!d||!d.success){alert(d&&d.data&&d.data.message?d.data.message:'Exporteren mislukt.');return}
                download(d.data.text,d.data.filename);
            }).catch(()=>{}));
    
            $('#dca-bulk-check').addEventListener('click',function(){
                if(!bulkOut.value.trim()){status(bulkStatus,'Er staat geen tekst om te controleren.','is-error');return}
                status(bulkStatus,'Controleren...','');
                bulkSave.disabled=true;
                bulkChecked=false;
    
                ajax('dca_txt_import_preview',{txt_content:bulkOut.value}).then(d=>{
                    if(!d||!d.success){status(bulkStatus,d&&d.data&&d.data.message?d.data.message:'Controle mislukt.','is-error');return}
                    renderPreview(bulkPreview,d.data.items);
                    const errors=d.data.items.some(i=>i.status!=='success');
                    if(errors){
                        bulkChecked=true;
                        bulkSave.disabled=false;
                        status(bulkStatus,'Er zijn fouten gevonden. Geldige items kunnen alsnog worden opgeslagen; foutieve items worden overgeslagen.','is-error');
                        return;
                    }
                    bulkChecked=true;
                    bulkSave.disabled=false;
                    status(bulkStatus,'Controle geslaagd. Klaar om bulk op te slaan.','is-success');
                }).catch(()=>status(bulkStatus,'Controle mislukt.','is-error'));
            });
    
            bulkSave.addEventListener('click',function(){
                if(!bulkChecked){status(bulkStatus,'Controleer eerst de bulktekst.','is-error');return}
                if(!confirm('Weet je zeker dat je deze bulk-tekst wilt opslaan? Geldige items worden overschreven. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.'))return;
    
                this.disabled=true;
                status(bulkStatus,'Back-ups maken en bulk opslaan...','');
    
                ajax('dca_txt_import_run',{txt_content:bulkOut.value}).then(d=>{
                    this.disabled=false;
                    if(!d||!d.success){status(bulkStatus,d&&d.data&&d.data.message?d.data.message:'Bulk opslaan mislukt.','is-error');return}
                    if(d.data&&d.data.items){renderPreview(bulkPreview,d.data.items)}
                    bulkInitial=bulkOut.value;
                    clearBulkDraft();
                    status(bulkStatus,d.data.message||'Bulk opgeslagen.','is-success');
                    reloadList('Bulk opgeslagen');
                }).catch(()=>{this.disabled=false;status(bulkStatus,'Bulk opslaan mislukt.','is-error')});
            });
    
            $('#dca-bulk-copy').addEventListener('click',()=>{bulkOut.focus();bulkOut.select();copy(bulkOut.value,bulkStatus)});
            $('#dca-bulk-download').addEventListener('click',()=>{download(bulkOut.value,bulkFilename);status(bulkStatus,'TXT-bestand gedownload.','is-success')});
            $('.dca-close-bulk').addEventListener('click',()=>closeSafe(bulkModal,'bulk'));
    
            $('#dca-open-import').addEventListener('click',()=>{importTxt='';importOk=false;importFile.value='';importPreviewBox.innerHTML='';importPreviewBox.style.display='none';importRun.disabled=true;status(importStatus,'','');open(importModal)});
            $('.dca-close-import').addEventListener('click',()=>close(importModal));
    
            function readFile(){
                return new Promise((res,rej)=>{
                    const f=importFile.files&&importFile.files[0];
                    if(!f)return rej('Kies eerst een TXT-bestand.');
                    if(!f.name.toLowerCase().endsWith('.txt'))return rej('Kies een geldig .txt-bestand.');
                    const r=new FileReader();
                    r.onload=()=>res(String(r.result||''));
                    r.onerror=()=>rej('Bestand kon niet gelezen worden.');
                    r.readAsText(f);
                });
            }
    
            $('#dca-import-preview').addEventListener('click',function(){
                importTxt='';
                importOk=false;
                importPreviewBox.innerHTML='';
                importPreviewBox.style.display='none';
                importRun.disabled=true;
                status(importStatus,'Bestand lezen...','');
    
                readFile().then(txt=>{
                    importTxt=txt;
                    status(importStatus,'Bestand controleren...','');
                    return ajax('dca_txt_import_preview',{txt_content:txt});
                }).then(d=>{
                    if(!d||!d.success){status(importStatus,d&&d.data&&d.data.message?d.data.message:'Controle mislukt.','is-error');return}
                    renderPreview(importPreviewBox,d.data.items);
                    const errors=d.data.items.some(i=>i.status!=='success');
                    if(errors){
                        importOk=true;
                        importRun.disabled=false;
                        status(importStatus,'Er zijn fouten gevonden. Geldige items kunnen alsnog worden geïmporteerd; foutieve items worden overgeslagen.','is-error');
                        return;
                    }
                    importOk=true;
                    importRun.disabled=false;
                    status(importStatus,'Controle geslaagd. Klaar om te importeren.','is-success');
                }).catch(m=>status(importStatus,m||'Bestand kon niet gelezen worden.','is-error'));
            });
    
            importRun.addEventListener('click',function(){
                if(!importOk||!importTxt){status(importStatus,'Controleer eerst het bestand.','is-error');return}
                if(!confirm('Weet je zeker dat je dit TXT-bestand wilt importeren? Geldige items worden overschreven. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.'))return;
    
                this.disabled=true;
                status(importStatus,'Back-ups maken en importeren...','');
    
                ajax('dca_txt_import_run',{txt_content:importTxt}).then(d=>{
                    if(!d||!d.success){status(importStatus,d&&d.data&&d.data.message?d.data.message:'Import mislukt.','is-error');this.disabled=false;return}
                    if(d.data&&d.data.items){renderPreview(importPreviewBox,d.data.items)}
                    status(importStatus,d.data.message||'Import voltooid.','is-success');
                    reloadList('Import voltooid');
                }).catch(()=>{status(importStatus,'Import mislukt.','is-error');this.disabled=false});
            });
    
            [singleModal,bulkModal,importModal].forEach(m=>m.addEventListener('click',e=>{
                if(e.target===m){
                    if(m===singleModal)closeSafe(m,'single');
                    else if(m===bulkModal)closeSafe(m,'bulk');
                    else close(m);
                }
            }));
    
            document.addEventListener('keydown',e=>{
                if(e.key==='Escape'){
                    if(singleModal.classList.contains('is-active'))closeSafe(singleModal,'single');
                    if(bulkModal.classList.contains('is-active'))closeSafe(bulkModal,'bulk');
                    if(importModal.classList.contains('is-active'))close(importModal);
                }
            });
    
            window.addEventListener('beforeunload',e=>{
                if(dirty('single')||dirty('bulk')){
                    e.preventDefault();
                    e.returnValue='';
                }
            });
        });
