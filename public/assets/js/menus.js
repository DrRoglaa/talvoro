(() => {
  const form=document.querySelector('[data-menu-item-form]'); if(!form) return;
  const type=form.querySelector('[data-menu-target-type]');
  const groups=[...form.querySelectorAll('[data-menu-target]')];
  let timer=0;

  function sync(){
    const value=type.value;
    groups.forEach(group=>{
      const active=group.dataset.menuTarget===value;
      group.hidden=!active;
      group.querySelectorAll('[data-target-name]').forEach(el=>{
        if(active) el.setAttribute('name',el.dataset.targetName); else el.removeAttribute('name');
      });
    });
    const structured=form.querySelector('[data-menu-target="content_entry"] select');
    const model=form.querySelector('[data-target-model]');
    if(value==='content_entry'&&structured&&model){
      const option=structured.options[structured.selectedIndex]; model.value=option?.dataset.modelId||'';
    } else if(model) model.value='';
  }

  async function search(input){
    const group=input.closest('[data-menu-target]');
    const select=group?.querySelector('[data-menu-target-select]');
    if(!group||!select) return;
    const url=input.dataset.searchUrl; const kind=input.dataset.targetKind; if(!url||!kind) return;
    input.setAttribute('aria-busy','true');
    try{
      const response=await fetch(`${url}?type=${encodeURIComponent(kind)}&q=${encodeURIComponent(input.value.trim())}`,{headers:{Accept:'application/json'}});
      if(!response.ok) return;
      const payload=await response.json(); if(!payload?.ok||!Array.isArray(payload.items)) return;
      const first=document.createElement('option'); first.value=''; first.textContent='Choose content…';
      select.replaceChildren(first,...payload.items.map(item=>{
        const option=document.createElement('option'); option.value=String(item.id); option.textContent=item.secondary?`${item.label} — ${item.secondary}`:item.label;
        if(item.model_id) option.dataset.modelId=String(item.model_id); return option;
      }));
      sync();
    }catch(_error){/* Keep the existing bounded choices when search is unavailable. */}
    finally{input.removeAttribute('aria-busy');}
  }

  type.addEventListener('change',sync);
  form.addEventListener('change',event=>{if(event.target.closest('[data-menu-target="content_entry"]')) sync();});
  form.querySelectorAll('[data-menu-target-search]').forEach(input=>{
    input.addEventListener('input',()=>{window.clearTimeout(timer); timer=window.setTimeout(()=>search(input),220);});
    input.addEventListener('focus',()=>{if(input.value.trim()!=='') search(input);});
  });
  sync();
})();
