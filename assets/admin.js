document.addEventListener('DOMContentLoaded',function(){
            const dcaSettings = window.dcaTbSettings || {};
            const nonce = dcaSettings.nonce;
            const filterUrl = dcaSettings.filterUrl;
            const notDoneUrl = dcaSettings.notDoneUrl;
            const filterLabel = dcaSettings.filterLabel;
            const ajaxUrl = window.ajaxurl || dcaSettings.ajaxUrl || '';
    
            const $=s=>document.querySelector(s), $$=s=>Array.from(document.querySelectorAll(s));

            function ensureToolbar(){
                if(!nonce)return;
                const existing=document.querySelector('.dca-toolbar');
                if(existing && document.querySelector('#dca-copy-selected') && document.querySelector('#dca-open-empty-bulk') && document.querySelector('#dca-export-selected') && document.querySelector('#dca-deselect-selected') && document.querySelector('#dca-open-import')){
                    return;
                }
                const bar=document.createElement('div');
                bar.className='dca-toolbar';
                const title=document.createElement('span');
                title.className='dca-toolbar-title';
                title.textContent='SEO:';
                bar.appendChild(title);
                [
                    ['dca-copy-selected','Kopieer','button'],
                    ['dca-open-empty-bulk','Bulkeditor','button'],
                    ['dca-export-selected','Export .txt','button'],
                    ['dca-deselect-selected','Deselecteer alles','button'],
                    ['dca-open-import','Import .txt','button button-primary']
                ].forEach(item=>{
                    const btn=document.createElement('button');
                    btn.type='button';
                    btn.id=item[0];
                    btn.className=item[2];
                    btn.textContent=item[1];
                    bar.appendChild(btn);
                });
                const link=document.createElement('a');
                link.className='button';
                link.href=filterUrl||'#';
                link.textContent=filterLabel||'Filter';
                bar.appendChild(link);
                document.body.appendChild(bar);
            }

            ensureToolbar();
    
            const toast=$('#dca-toast'), singleModal=$('#dca-single-modal'), singleOut=$('#dca-single-output'), singleTitle=$('#dca-single-title'), singleStatus=$('#dca-single-status'), singleView=$('#dca-single-view');
            const bulkModal=$('#dca-bulk-modal'), bulkOut=$('#dca-bulk-output'), bulkStatus=$('#dca-bulk-status'), bulkPreview=$('#dca-bulk-preview'), bulkSave=$('#dca-bulk-save');
            const importModal=$('#dca-import-modal'), importFile=$('#dca-import-file'), importStatus=$('#dca-import-status'), importPreviewBox=$('#dca-import-preview-box'), importRun=$('#dca-import-run');

            const singleSave=$('#dca-single-save'), singleCopy=$('#dca-single-copy'), singleDownload=$('#dca-single-download'), singleClose=$('.dca-close-single');
            const bulkCheck=$('#dca-bulk-check'), bulkCopy=$('#dca-bulk-copy'), bulkDownload=$('#dca-bulk-download'), bulkClose=$('.dca-close-bulk');
            const importPreview=$('#dca-import-preview'), importClose=$('.dca-close-import');
            const toolbarCopy=$('#dca-copy-selected'), toolbarBulk=$('#dca-open-empty-bulk'), toolbarExport=$('#dca-export-selected'), toolbarDeselect=$('#dca-deselect-selected'), toolbarImport=$('#dca-open-import');
            const requiredEls=[toast,singleModal,singleOut,singleTitle,singleStatus,singleView,singleSave,singleCopy,singleDownload,singleClose,bulkModal,bulkOut,bulkStatus,bulkPreview,bulkSave,bulkCheck,bulkCopy,bulkDownload,bulkClose,importModal,importFile,importStatus,importPreviewBox,importRun,importPreview,importClose,toolbarCopy,toolbarBulk,toolbarExport,toolbarDeselect,toolbarImport];
            if(!nonce||!ajaxUrl||requiredEls.some(el=>!el)){
                console.warn('Content Sync Manager: admin UI niet volledig geladen. Herlaad de adminpagina.');
                return;
            }
    
            let currentPostId=null, singleFilename='content-sync.txt', bulkFilename='content-sync.txt', importTxt='', importOk=false, importPreviewHash='', bulkPreviewHash='', cache={}, singleInitial='', bulkInitial='', bulkChecked=false, toastTimer=null, lastFocusedBeforeModal=null;
    
            function showToast(msg){toast.textContent=msg;toast.classList.add('is-active');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.remove('is-active'),3500)}
            function focusable(modal){return Array.from(modal.querySelectorAll('a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(el=>el.offsetParent!==null)}
            function open(m){lastFocusedBeforeModal=document.activeElement;m.classList.add('is-active');m.setAttribute('aria-hidden','false');const els=focusable(m);if(els.length){setTimeout(()=>els[0].focus(),0)}}
            function close(m){m.classList.remove('is-active');m.setAttribute('aria-hidden','true');if(lastFocusedBeforeModal&&typeof lastFocusedBeforeModal.focus==='function'){lastFocusedBeforeModal.focus()}lastFocusedBeforeModal=null}
            function status(el,msg,type){el.textContent=msg||'';el.classList.remove('is-success','is-error');if(type)el.classList.add(type)}
            function dirty(type){return type==='single'&&singleOut.value!==singleInitial||type==='bulk'&&bulkOut.value!==bulkInitial}
            function closeSafe(modal,type){if(dirty(type)&&!confirm('Je hebt wijzigingen die nog niet zijn opgeslagen. Toch sluiten?'))return;close(modal)}
            function ajax(action,data){
                const fd=new FormData();
                fd.append('action',action);
                fd.append('nonce',nonce);
                Object.keys(data||{}).forEach(k=>Array.isArray(data[k])?data[k].forEach(v=>fd.append(k+'[]',v)):fd.append(k,data[k]));
    
                return fetch(ajaxUrl,{
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
            function fallbackCopy(text){
                const ta=document.createElement('textarea');
                ta.value=text;
                ta.setAttribute('readonly','readonly');
                ta.style.position='fixed';
                ta.style.left='-9999px';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                let ok=false;
                try{ok=document.execCommand('copy')}catch(e){ok=false}
                ta.remove();
                return ok;
            }
            function copy(text,el){
                if(navigator.clipboard&&navigator.clipboard.writeText){
                    navigator.clipboard.writeText(text).then(()=>status(el,'Gekopieerd.','is-success')).catch(()=>{
                        const ok=fallbackCopy(text);
                        status(el,ok?'Gekopieerd.':'Kopiëren mislukt. Selecteer en kopieer handmatig.',ok?'is-success':'is-error');
                    });
                    return;
                }
                const ok=fallbackCopy(text);
                status(el,ok?'Gekopieerd.':'Kopiëren mislukt. Selecteer en kopieer handmatig.',ok?'is-success':'is-error');
            }
            function download(text,name){const b=new Blob([text],{type:'text/plain;charset=utf-8'}),u=URL.createObjectURL(b),a=document.createElement('a');a.href=u;a.download=name||'content-sync.txt';document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(u)}
            function selectedIds(){return $$('tbody th.check-column input[type="checkbox"][name="post[]"]:checked').map(c=>c.value)}
            function esc(v){return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
            function slug(v){return String(v).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')}
    
            function reloadList(msg){showToast((msg||'Opgeslagen')+'. Lijst wordt bijgewerkt...');setTimeout(function(){window.location.href=window.location.href},900)}
            function updateSelectionToast(){showToast('Content Syncken: '+selectedIds().length+' geselecteerd')}
            function deselectAll(){
                $$('tbody th.check-column input[type="checkbox"][name="post[]"], #cb-select-all-1, #cb-select-all-2').forEach(c=>{c.checked=false;c.indeterminate=false});
                updateSelectionToast();
            }
    
            document.addEventListener('change',e=>{if(e.target.matches('input[type="checkbox"][name="post[]"], #cb-select-all-1, #cb-select-all-2'))setTimeout(updateSelectionToast,40)});
            toolbarDeselect.addEventListener('click',e=>{e.preventDefault();deselectAll()});
    
            function preload(){
                const ids=$$('.dca-open-acf-textblock').map(b=>b.dataset.postId).filter(Boolean);
                if(!ids.length)return;
                ajax('dca_preload_acf_textblocks',{post_ids:ids}).then(d=>{if(d&&d.success&&d.data.items)cache=d.data.items}).catch(()=>{});
            }
    
            // AI-PATCH: automatische preload uitgeschakeld.
            // Op grote sites bouwde dit direct voor alle zichtbare rijen zware ACF/media/Yoast exports via AJAX,
            // waardoor pagina-/bericht-/productlijsten konden vastlopen. Content wordt nu pas opgehaald bij openen/exporteren.
    
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
    
            function importableItems(items){
                return Array.isArray(items) ? items.filter(i=>i && (i.status==='success'||i.status==='partial')) : [];
            }

            function previewSummary(items){
                if(!Array.isArray(items)||!items.length)return '';
                const ok=importableItems(items).length;
                const blocked=items.length-ok;
                return ok+' importeerbaar, '+blocked+' geblokkeerd.';
            }

            function setButtonEnabled(button,enabled){
                button.disabled=!enabled;
                if(enabled){
                    button.removeAttribute('disabled');
                }else{
                    button.setAttribute('disabled','disabled');
                }
            }

            function renderPreview(box,items){
                if(!Array.isArray(items)){
                    box.innerHTML='<p class="dca-error">Geen geldige preview ontvangen.</p>';
                    box.style.display='block';
                    return;
                }
                const summary=previewSummary(items);
                let html=summary?'<p><strong>Controle:</strong> '+esc(summary)+'</p>':'';
                html+='<table><thead><tr><th>Bron</th><th>Gekoppelde pagina</th><th>Status</th></tr></thead><tbody>';
                items.forEach(i=>{
                    i=i||{};
                    const cls=i.status==='success'?'dca-ok':(i.status==='partial'?'dca-partial':'dca-error');
                    const target=i.target_title?i.target_title+' (#'+(i.target_post_id||0)+')':'Niet gevonden';
                    html+='<tr><td><strong>'+esc(i.source_title||'Onbekend item')+'</strong><br>ID: '+esc(i.source_id||'')+'</td><td>'+esc(target)+'</td><td class="'+cls+'">'+esc(i.message||'Geen melding ontvangen.')+'</td></tr>';
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
    
            singleSave.addEventListener('click',function(){
                if(!currentPostId){status(singleStatus,'Geen pagina geselecteerd.','is-error');return}
                if(!confirm('Weet je zeker dat je dit contentblok wilt opslaan? Er wordt automatisch eerst een back-up gemaakt.'))return;
                this.disabled=true;status(singleStatus,'Back-up maken en opslaan...','');
    
                ajax('dca_save_acf_textblock',{post_id:currentPostId,textblock:singleOut.value,destructive_confirm:'1'}).then(d=>{
                    this.disabled=false;
                    if(!d||!d.success){status(singleStatus,d&&d.data&&d.data.message?d.data.message:'Opslaan mislukt.','is-error');return}
                    singleInitial=singleOut.value;
                    cache[currentPostId]={title:singleTitle.textContent.replace('Content Sync: ',''),text:singleOut.value,view_url:singleView.href};
                    status(singleStatus,d.data.message||'Opgeslagen.','is-success');
                    reloadList('Pagina opgeslagen');
                }).catch(()=>{this.disabled=false;status(singleStatus,'Opslaan mislukt.','is-error')});
            });
    
            singleCopy.addEventListener('click',()=>{singleOut.focus();singleOut.select();copy(singleOut.value,singleStatus)});
            singleDownload.addEventListener('click',()=>{download(singleOut.value,singleFilename);status(singleStatus,'TXT-bestand gedownload.','is-success')});
            singleClose.addEventListener('click',()=>closeSafe(singleModal,'single'));
    
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
    
            function resetBulkCheck(){bulkChecked=false;bulkPreviewHash='';setButtonEnabled(bulkSave,false);bulkPreview.style.display='none';bulkPreview.innerHTML=''}
            bulkOut.addEventListener('input',function(){resetBulkCheck();saveBulkDraft()});
    
            toolbarBulk.addEventListener('click',function(){
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
    
            toolbarCopy.addEventListener('click',function(){
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
    
            toolbarExport.addEventListener('click',()=>fetchBulk().then(d=>{
                if(!d||!d.success){alert(d&&d.data&&d.data.message?d.data.message:'Exporteren mislukt.');return}
                download(d.data.text,d.data.filename);
            }).catch(()=>{}));
    
            bulkCheck.addEventListener('click',function(){
                if(!bulkOut.value.trim()){status(bulkStatus,'Er staat geen tekst om te controleren.','is-error');return}
                status(bulkStatus,'Controleren...','');
                setButtonEnabled(bulkSave,false);
                bulkChecked=false;
                bulkPreviewHash='';
    
                ajax('dca_txt_import_preview',{txt_content:bulkOut.value}).then(d=>{
                    if(!d||!d.success){bulkPreview.style.display='none';bulkPreview.innerHTML='';status(bulkStatus,d&&d.data&&d.data.message?d.data.message:'Controle mislukt.','is-error');return}
                    const items=Array.isArray(d.data&&d.data.items)?d.data.items:[];
                    bulkPreviewHash=String((d.data&&d.data.preview_hash)||'');
                    renderPreview(bulkPreview,items);
                    if(!items.length){status(bulkStatus,'Controle gaf geen items terug. Opslaan is geblokkeerd.','is-error');return}
                    const validItems=importableItems(items).length>0;
                    const errors=items.some(i=>i.status!=='success');
                    bulkChecked=validItems&&!!bulkPreviewHash;
                    setButtonEnabled(bulkSave,bulkChecked);
                    if(errors){
                        status(bulkStatus, validItems ? 'Controle klaar: '+previewSummary(items)+' Geldige items kunnen worden opgeslagen; geblokkeerde items worden overgeslagen.' : 'Controle klaar: '+previewSummary(items)+' Er is niets om op te slaan. Bekijk de rode meldingen per item.','is-error');
                        return;
                    }
                    status(bulkStatus,'Controle geslaagd. '+previewSummary(items)+' Klaar om bulk op te slaan.','is-success');
                }).catch(()=>status(bulkStatus,'Controle mislukt.','is-error'));
            });
    
            bulkSave.addEventListener('click',function(){
                if(!bulkChecked||!bulkPreviewHash){status(bulkStatus,'Controleer eerst exact deze bulktekst opnieuw.','is-error');return}
                if(!confirm('Weet je zeker dat je deze gecontroleerde bulk-tekst wilt opslaan? Geldige items kunnen bestaande content, SEO-, ACF- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.'))return;
    
                this.disabled=true;
                status(bulkStatus,'Back-ups maken en bulk opslaan...','');
    
                ajax('dca_txt_import_run',{txt_content:bulkOut.value,preview_hash:bulkPreviewHash,destructive_confirm:'1'}).then(d=>{
                    this.disabled=false;
                    if(!d||!d.success){status(bulkStatus,d&&d.data&&d.data.message?d.data.message:'Bulk opslaan mislukt.','is-error');return}
                    if(d.data&&d.data.items){renderPreview(bulkPreview,d.data.items)}
                    bulkInitial=bulkOut.value;
                    clearBulkDraft();
                    status(bulkStatus,d.data.message||'Bulk opgeslagen.','is-success');
                    reloadList('Bulk opgeslagen');
                }).catch(()=>{this.disabled=false;status(bulkStatus,'Bulk opslaan mislukt.','is-error')});
            });
    
            bulkCopy.addEventListener('click',()=>{bulkOut.focus();bulkOut.select();copy(bulkOut.value,bulkStatus)});
            bulkDownload.addEventListener('click',()=>{download(bulkOut.value,bulkFilename);status(bulkStatus,'TXT-bestand gedownload.','is-success')});
            bulkClose.addEventListener('click',()=>closeSafe(bulkModal,'bulk'));
    
            toolbarImport.addEventListener('click',()=>{importTxt='';importOk=false;importPreviewHash='';importFile.value='';importPreviewBox.innerHTML='';importPreviewBox.style.display='none';setButtonEnabled(importRun,false);status(importStatus,'','');open(importModal)});
            importClose.addEventListener('click',()=>close(importModal));
    
            function readFile(){
                return new Promise((res,rej)=>{
                    const f=importFile.files&&importFile.files[0];
                    if(!f)return rej('Kies eerst een TXT-bestand.');
                    if(!f.name.toLowerCase().endsWith('.txt'))return rej('Kies een geldig .txt-bestand.');
                    if(dcaSettings.maxImportBytes&&f.size>Number(dcaSettings.maxImportBytes))return rej('Bestand is te groot. Maximaal toegestaan: '+Math.round(Number(dcaSettings.maxImportBytes)/1024/1024)+' MB.');
                    const r=new FileReader();
                    r.onload=()=>res(String(r.result||''));
                    r.onerror=()=>rej('Bestand kon niet gelezen worden.');
                    r.readAsText(f);
                });
            }
    
            importPreview.addEventListener('click',function(){
                importTxt='';
                importOk=false;
                importPreviewHash='';
                importPreviewBox.innerHTML='';
                importPreviewBox.style.display='none';
                setButtonEnabled(importRun,false);
                status(importStatus,'Bestand lezen...','');
    
                readFile().then(txt=>{
                    importTxt=txt;
                    status(importStatus,'Bestand controleren...','');
                    return ajax('dca_txt_import_preview',{txt_content:txt});
                }).then(d=>{
                    if(!d||!d.success){importPreviewBox.style.display='none';importPreviewBox.innerHTML='';status(importStatus,d&&d.data&&d.data.message?d.data.message:'Controle mislukt.','is-error');return}
                    const items=Array.isArray(d.data&&d.data.items)?d.data.items:[];
                    importPreviewHash=String((d.data&&d.data.preview_hash)||'');
                    renderPreview(importPreviewBox,items);
                    if(!items.length){status(importStatus,'Controle gaf geen items terug. Importeren is geblokkeerd.','is-error');return}
                    const validItems=importableItems(items).length>0;
                    const errors=items.some(i=>i.status!=='success');
                    importOk=validItems&&!!importPreviewHash;
                    setButtonEnabled(importRun,importOk);
                    if(errors){
                        status(importStatus, validItems ? 'Controle klaar: '+previewSummary(items)+' Geldige items kunnen worden geïmporteerd; geblokkeerde items worden overgeslagen.' : 'Controle klaar: '+previewSummary(items)+' Er is niets om te importeren. Bekijk de rode meldingen per item.','is-error');
                        return;
                    }
                    status(importStatus,'Controle geslaagd. '+previewSummary(items)+' Klaar om te importeren.','is-success');
                }).catch(m=>status(importStatus,m||'Bestand kon niet gelezen worden.','is-error'));
            });
    
            importRun.addEventListener('click',function(){
                if(!importOk||!importTxt||!importPreviewHash){status(importStatus,'Controleer eerst exact dit bestand opnieuw.','is-error');return}
                if(!confirm('Weet je zeker dat je dit gecontroleerde TXT-bestand wilt importeren? Geldige items kunnen bestaande content, SEO-, ACF- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.'))return;
    
                this.disabled=true;
                status(importStatus,'Back-ups maken en importeren...','');
    
                ajax('dca_txt_import_run',{txt_content:importTxt,preview_hash:importPreviewHash,destructive_confirm:'1'}).then(d=>{
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
                const activeModal = [singleModal,bulkModal,importModal].find(m=>m.classList.contains('is-active'));

                if(e.key==='Escape'){
                    if(singleModal.classList.contains('is-active'))closeSafe(singleModal,'single');
                    else if(bulkModal.classList.contains('is-active'))closeSafe(bulkModal,'bulk');
                    else if(importModal.classList.contains('is-active'))close(importModal);
                }

                if(e.key==='Tab'&&activeModal){
                    const els=focusable(activeModal);
                    if(!els.length)return;
                    const first=els[0], last=els[els.length-1];
                    if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}
                    else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}
                }
            });
    
            window.addEventListener('beforeunload',e=>{
                if(dirty('single')||dirty('bulk')){
                    e.preventDefault();
                    e.returnValue='';
                }
            });
        });
