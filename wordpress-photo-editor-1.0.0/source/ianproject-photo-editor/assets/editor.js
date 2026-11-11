/* IanProject Photo Editor — local canvas processing, no external libraries. GPL-2.0-or-later. */
(() => {
  'use strict';
  const root = document.getElementById('ian-photo-editor');
  if (!root) return;
  const config = window.IANPE_CONFIG || { wordpress: false };
  const $ = id => document.getElementById('ipe-' + id);
  const stage = $('stage'), ctx = stage.getContext('2d'), result = $('result');
  const work = document.createElement('canvas');
  const extensions = {'image/jpeg':'jpg','image/png':'png','image/webp':'webp'};
  let items = [], active = null, originalImage = null, loadToken = 0, busy = false, dragging = null, view = null, disabledBeforeBusy = null, previewMode = false;
  const clamp = (v,a,b) => Math.min(b, Math.max(a, v));
  const number = (id, fallback) => Number.isFinite(Number($(id).value)) ? Number($(id).value) : fallback;
  const current = () => items.find(i => i.id === active);
  const stateOf = i => ({crop:i.crop ? {...i.crop}:null,rotation:i.rotation,flip:i.flip,ratio:i.ratio});
  const same = (a,b) => JSON.stringify(a) === JSON.stringify(b);
  const bytes = n => n >= 1048576 ? (n / 1048576).toFixed(2) + ' MB' : Math.round(n / 1024) + ' KB';
  function status(message, error=false) { $('status').textContent = message; $('status').style.color = error ? '#a13131' : '#0d6b68'; }
  function remember(item, before) { if (same(before,stateOf(item))) return; item.history.push(before); if(item.history.length>30)item.history.shift(); item.future=[]; item.edited=true; }
  function ratio() { const v=$('ratio').value; return v==='free'?0:v==='output'?number('width',1600)/number('height',1200):Number(v); }
  function fitRect(r, aspect=0) {
    let w=clamp(r.w,1,work.width), h=clamp(r.h,1,work.height);
    if(aspect){if(w/h>aspect)w=h*aspect;else h=w/aspect;}
    return {x:clamp(r.x,0,work.width-w),y:clamp(r.y,0,work.height-h),w,h};
  }
  function centerRect(aspect=0) { let w=work.width,h=work.height;if(aspect){if(w/h>aspect)w=h*aspect;else h=w/aspect;}return {x:(work.width-w)/2,y:(work.height-h)/2,w,h}; }
  function constrainAspect(r, aspect) { let w=r.w,h=r.h;if(w/h>aspect)w=h*aspect;else h=w/aspect;return fitRect({x:r.x+(r.w-w)/2,y:r.y+(r.h-h)/2,w,h},aspect); }
  function paintWork(image,item) {
    const odd=item.rotation%2;
    work.width=odd?image.naturalHeight:image.naturalWidth;work.height=odd?image.naturalWidth:image.naturalHeight;
    const c=work.getContext('2d');c.translate(work.width/2,work.height/2);c.scale(item.flip?-1:1,1);c.rotate(item.rotation*Math.PI/2);c.drawImage(image,-image.naturalWidth/2,-image.naturalHeight/2);
  }
  async function decode(item) {
    const img = new Image(); if(item.mediaId)img.crossOrigin='anonymous';img.decoding='async';img.src=item.url;
    try{await img.decode();}catch(e){throw Error('无法读取 '+item.name+'。媒体库图片若来自外部 CDN，需要允许跨域；也可以下载原图后从电脑选图。');}
    if(!img.naturalWidth||!img.naturalHeight||img.naturalWidth*img.naturalHeight>40000000)throw Error('原图超过 4000 万像素或无法解码，请先使用较小的 JPEG / PNG / WebP。');
    return img;
  }
  async function selectItem(id) {
    const item=items.find(i=>i.id===id);if(!item)return;
    const token=++loadToken;active=id;originalImage=null;renderQueue();$('current-name').textContent=item.name;$('source-info').textContent='正在读取原图…';
    try{
      const image=await decode(item);if(token!==loadToken)return;
      originalImage=image;$('ratio').value=item.ratio;paintWork(image,item);if(!item.crop)item.crop=centerRect(ratio());else item.crop=fitRect(item.crop);
      $('source-info').textContent=image.naturalWidth+' × '+image.naturalHeight+' px'+(item.file?' · '+bytes(item.file.size):' · 媒体库原图');
      $('empty').hidden=true;refresh();
    }catch(e){if(token===loadToken){originalImage=null;$('source-info').textContent='读取失败';status(e.message,true);refreshEnabled();}}
  }
  function renderQueue() {
    const queue=$('queue');queue.replaceChildren();
    items.forEach((item,index)=>{
      const box=document.createElement('div');box.className='ipe-queue-item'+(item.id===active?' is-active':'');
      const b=document.createElement('button');b.type='button';b.title=item.name;b.setAttribute('aria-label','编辑 '+item.name);b.setAttribute('aria-pressed',String(item.id===active));
      const im=document.createElement('img');im.src=item.url;im.alt='';const label=document.createElement('span');label.textContent=(index+1)+'. '+item.name;const flag=document.createElement('span');flag.textContent=item.edited?'已调整':'原图';b.append(im,label,flag);b.onclick=()=>{if(!busy)selectItem(item.id);};
      const remove=document.createElement('button');remove.type='button';remove.className='ipe-remove';remove.textContent='×';remove.setAttribute('aria-label','移除 '+item.name);remove.onclick=()=>removeItem(item.id);
      box.append(b,remove);queue.append(box);
    });
    $('queue-info').textContent=items.length?'共 '+items.length+' 张 · 逐张调整选区后可批量保存':'支持 JPEG、PNG、WebP，可一次添加多张';
    refreshEnabled();
  }
  async function addSources(sources) {
    if(busy)return;let added=[];let errors=[];
    for(const s of sources){
      if(items.length>=24){errors.push('每次最多处理 24 张图片。');break;}
      if(s.file&&(!Object.keys(extensions).includes(s.file.type)||s.file.size>30*1048576)){errors.push(s.file.name+'：请选择 30 MB 以内的 JPEG / PNG / WebP。');continue;}
      if(items.reduce((a,i)=>a+(i.file?.size||0),0)+(s.file?.size||0)>150*1048576){errors.push('本次原图总大小超过 150 MB，请分批处理。');break;}
      const item={id:'photo-'+Date.now()+'-'+Math.random().toString(36).slice(2),file:s.file||null,url:s.file?URL.createObjectURL(s.file):s.url,name:s.file?s.file.name:s.name,mediaId:s.id||0,alt:s.alt||'',rotation:0,flip:false,ratio:'free',crop:null,history:[],future:[],edited:false};
      items.push(item);added.push(item);
    }
    renderQueue();if(added.length)await selectItem(added[0].id);if(errors.length)status(errors.join(' '),true);else if(added.length)status('已添加 '+added.length+' 张。选区外的内容不会导出，原文件不变。');
  }
  async function removeItem(id){if(busy)return;const index=items.findIndex(i=>i.id===id),item=items[index];if(!item)return;if(item.file)URL.revokeObjectURL(item.url);items.splice(index,1);renderQueue();if(id===active){if(items.length)await selectItem(items[Math.min(index,items.length-1)].id);else clearWorkspace();}}
  function clearWorkspace(){++loadToken;active=null;originalImage=null;work.width=0;work.height=0;ctx.clearRect(0,0,stage.width,stage.height);result.width=1;result.height=1;$('empty').hidden=false;$('current-name').textContent='选择一张图片开始';$('source-info').textContent='';$('output-info').textContent='选择图片后预览导出效果';$('warning').textContent='';refreshEnabled();}
  function refreshEnabled(){const ok=!!(current()&&originalImage);['trim','reset','left','right','flip','x','y','cw','ch','download','save'].forEach(id=>$(id).disabled=busy||!ok);['save-all','download-all'].forEach(id=>$(id).disabled=busy||!items.length);$('clear').disabled=busy||!items.length;$('files').disabled=busy;$('media').disabled=busy;$('undo').disabled=busy||!ok||!current().history.length;$('redo').disabled=busy||!ok||!current().future.length;root.classList.toggle('is-busy',busy);}
  function displayHandles(r){return [['nw',r.x,r.y],['n',r.x+r.w/2,r.y],['ne',r.x+r.w,r.y],['e',r.x+r.w,r.y+r.h/2],['se',r.x+r.w,r.y+r.h],['s',r.x+r.w/2,r.y+r.h],['sw',r.x,r.y+r.h],['w',r.x,r.y+r.h/2]];}
  function draw(){
    const box=stage.getBoundingClientRect(),dpr=Math.min(window.devicePixelRatio||1,2);stage.width=Math.round(box.width*dpr);stage.height=Math.round(box.height*dpr);ctx.setTransform(dpr,0,0,dpr,0,0);ctx.clearRect(0,0,box.width,box.height);
    const item=current();if(!item||!originalImage||!item.crop)return;
    if(previewMode){const scale=Math.min((box.width-36)/result.width,(box.height-36)/result.height),w=result.width*scale,h=result.height*scale,x=(box.width-w)/2,y=(box.height-h)/2;ctx.save();ctx.beginPath();ctx.rect(x,y,w,h);ctx.clip();for(let yy=0;yy<h;yy+=16)for(let xx=0;xx<w;xx+=16){ctx.fillStyle=(Math.floor(xx/16)+Math.floor(yy/16))%2?'#e4ebe5':'#fff';ctx.fillRect(x+xx,y+yy,16,16);}ctx.drawImage(result,x,y,w,h);ctx.restore();return;}
    const scale=Math.min((box.width-36)/work.width,(box.height-36)/work.height);view={x:(box.width-work.width*scale)/2,y:(box.height-work.height*scale)/2,scale};
    ctx.fillStyle='#fff';ctx.fillRect(view.x,view.y,work.width*scale,work.height*scale);ctx.drawImage(work,view.x,view.y,work.width*scale,work.height*scale);
    const r={x:view.x+item.crop.x*scale,y:view.y+item.crop.y*scale,w:item.crop.w*scale,h:item.crop.h*scale};
    ctx.fillStyle='rgba(9,31,25,.48)';ctx.beginPath();ctx.rect(view.x,view.y,work.width*scale,work.height*scale);ctx.rect(r.x+r.w,r.y,-r.w,r.h);ctx.fill('evenodd');
    ctx.strokeStyle='#fff';ctx.lineWidth=1.5;ctx.strokeRect(r.x,r.y,r.w,r.h);ctx.strokeStyle='rgba(255,255,255,.45)';ctx.lineWidth=.7;
    for(let i=1;i<3;i++){ctx.beginPath();ctx.moveTo(r.x+r.w*i/3,r.y);ctx.lineTo(r.x+r.w*i/3,r.y+r.h);ctx.moveTo(r.x,r.y+r.h*i/3);ctx.lineTo(r.x+r.w,r.y+r.h*i/3);ctx.stroke();}
    displayHandles(r).forEach(([,x,y])=>{ctx.fillStyle='#0d6b68';ctx.fillRect(x-5,y-5,10,10);ctx.strokeStyle='#fff';ctx.lineWidth=1.5;ctx.strokeRect(x-5,y-5,10,10);});
  }
  function settings(){const width=Math.round(number('width',1600)),height=Math.round(number('height',1200));if(width<16||height<16||width>8192||height>8192||width*height>24000000)throw Error('输出尺寸需为 16–8192 px，且总像素不超过 2400 万。');return{width,height,padding:clamp(number('padding',5),0,20)/100,fit:$('fit').value,mime:$('format').value,quality:clamp(number('quality',85),50,100)/100,background:$('background').value==='custom'?$('color').value:$('background').value};}
  function renderOutput(canvas,s,width=s.width,height=s.height){
    const r=current().crop;canvas.width=width;canvas.height=height;const c=canvas.getContext('2d');if(s.background!=='transparent'){c.fillStyle=s.background;c.fillRect(0,0,width,height);}const pad=Math.min(width,height)*s.padding,innerW=width-2*pad,innerH=height-2*pad;const scale=s.fit==='cover'?Math.max(innerW/r.w,innerH/r.h):Math.min(innerW/r.w,innerH/r.h);const w=r.w*scale,h=r.h*scale;
    c.save();c.beginPath();c.rect(pad,pad,innerW,innerH);c.clip();c.imageSmoothingEnabled=true;c.imageSmoothingQuality='high';c.drawImage(work,r.x,r.y,r.w,r.h,pad+(innerW-w)/2,pad+(innerH-h)/2,w,h);c.restore();
    return s.fit==='cover'?Math.max((s.width-2*Math.min(s.width,s.height)*s.padding)/r.w,(s.height-2*Math.min(s.width,s.height)*s.padding)/r.h):Math.min((s.width-2*Math.min(s.width,s.height)*s.padding)/r.w,(s.height-2*Math.min(s.width,s.height)*s.padding)/r.h);
  }
  function template(value,item,index){return value.replace(/\{(model|view|index|original)\}/g,(_,key)=>({model:$('model').value.trim(),view:$('view').value,index:String(Math.max(1,Math.round(number('start',1)))+index).padStart(2,'0'),original:item.name.replace(/\.[^.]+$/,'')}[key]));}
  function cleanName(value){return value.normalize('NFKC').replace(/[\x00-\x1f<>:"/\\|?*]/g,'-').replace(/\s+/g,'-').replace(/-+/g,'-').replace(/^[.\s-]+|[.\s-]+$/g,'').slice(0,150).toLowerCase()||'ianproject-image';}
  function outputName(item,index){const pattern=$('pattern').value||'ianproject-{original}-{index}';return cleanName(template(pattern,item,index))+'.'+extensions[$('format').value];}
  function plannedNames(){const used=new Set();return items.map((item,index)=>{const name=outputName(item,index),dot=name.lastIndexOf('.');let n=name,k=2;while(used.has(n)){n=name.slice(0,dot)+'-'+String(k++)+name.slice(dot);}used.add(n);return n;});}
  function refresh(){
    const item=current();refreshEnabled();if(!item||!originalImage||!item.crop)return;
    for(const [id,key] of [['x','x'],['y','y'],['cw','w'],['ch','h']])$(id).value=Math.round(item.crop[key]);draw();
    try{const s=settings(),scale=Math.min(1,850/s.width,540/s.height);const enlargement=renderOutput(result,s,Math.max(1,Math.round(s.width*scale)),Math.max(1,Math.round(s.height*scale)));$('output-info').textContent=s.width+' × '+s.height+' px · '+extensions[s.mime].toUpperCase()+' · '+(s.fit==='contain'?'完整展示':'裁剪填满');$('warning').textContent=enlargement>1.12?'当前选区约 '+Math.round(item.crop.w)+' × '+Math.round(item.crop.h)+' px，导出会放大 '+enlargement.toFixed(1)+' 倍，清晰度不会增加。建议缩小输出尺寸或换高清原图。':s.fit==='cover'?'填满画框会裁掉比例不一致的边缘，请核对成品预览。':'';const index=items.indexOf(item);$('filename').textContent=plannedNames()[index];if(previewMode)draw();}catch(e){$('warning').textContent=e.message;}
  }
  function point(e){const b=stage.getBoundingClientRect();return{x:clamp((e.clientX-b.left-view.x)/view.scale,0,work.width),y:clamp((e.clientY-b.top-view.y)/view.scale,0,work.height)};}
  stage.addEventListener('pointerdown',e=>{
    if(previewMode||busy||!originalImage||!view||!current()?.crop)return;e.preventDefault();const p=point(e),r=current().crop,tolerance=12/view.scale;const handle=displayHandles(r).find(([,x,y])=>Math.hypot(p.x-x,p.y-y)<tolerance)?.[0];const inside=p.x>=r.x&&p.x<=r.x+r.w&&p.y>=r.y&&p.y<=r.y+r.h;dragging={p,handle:handle||(inside?'move':'new'),start:{...r},before:stateOf(current())};stage.setPointerCapture(e.pointerId);
  });
  stage.addEventListener('pointermove',e=>{
    if(!dragging||!originalImage)return;const p=point(e),d=dragging,s=d.start,a=ratio();let r={...s};
    if(d.handle==='move'){r.x=clamp(s.x+p.x-d.p.x,0,work.width-s.w);r.y=clamp(s.y+p.y-d.p.y,0,work.height-s.h);}
    else if(d.handle==='new'){r={x:Math.min(p.x,d.p.x),y:Math.min(p.y,d.p.y),w:Math.max(1,Math.abs(p.x-d.p.x)),h:Math.max(1,Math.abs(p.y-d.p.y))};r=fitRect(r,a);}
    else{
      let left=s.x,right=s.x+s.w,top=s.y,bottom=s.y+s.h;
      if(d.handle.includes('w'))left=clamp(p.x,0,right-1);if(d.handle.includes('e'))right=clamp(p.x,left+1,work.width);if(d.handle.includes('n'))top=clamp(p.y,0,bottom-1);if(d.handle.includes('s'))bottom=clamp(p.y,top+1,work.height);
      r={x:left,y:top,w:right-left,h:bottom-top};
      if(a){if(d.handle==='n'||d.handle==='s'){r.w=r.h*a;r.x=s.x+s.w/2-r.w/2;}else{r.h=r.w/a;if(d.handle.includes('n'))r.y=s.y+s.h-r.h;else if(d.handle==='w'||d.handle==='e')r.y=s.y+s.h/2-r.h/2;}const limit=Math.min(1,work.width/r.w,work.height/r.h);r.w*=limit;r.h*=limit;r=fitRect(r,a);}
    }
    current().crop=r;refresh();
  });
  stage.addEventListener('pointerup',e=>{if(!dragging)return;const item=current();if(dragging.handle==='new'&&(item.crop.w<4||item.crop.h<4))item.crop=dragging.start;remember(item,dragging.before);dragging=null;if(stage.hasPointerCapture(e.pointerId))stage.releasePointerCapture(e.pointerId);renderQueue();refresh();});
  stage.addEventListener('pointercancel',()=>{if(dragging&&current()){current().crop=dragging.start;dragging=null;refresh();}});
  function editCrop(action){const item=current();if(!item||!originalImage||busy)return;const before=stateOf(item);action(item);item.ratio=$('ratio').value;remember(item,before);renderQueue();refresh();}
  function trimBounds(){
    const a=document.createElement('canvas'),scale=Math.min(1,1100/Math.max(work.width,work.height));a.width=Math.max(1,Math.round(work.width*scale));a.height=Math.max(1,Math.round(work.height*scale));const c=a.getContext('2d',{willReadFrequently:true});c.drawImage(work,0,0,a.width,a.height);const data=c.getImageData(0,0,a.width,a.height).data;let transparent=0;for(let n=3;n<data.length;n+=16)if(data[n]<240)transparent++;const hasAlpha=transparent/(data.length/16)>.01;const threshold=number('threshold',245);let l=a.width,t=a.height,r=-1,b=-1,count=0;
    for(let y=0;y<a.height;y++)for(let x=0;x<a.width;x++){const i=(y*a.width+x)*4;if(data[i+3]>20&&(hasAlpha||Math.min(data[i],data[i+1],data[i+2])<threshold)){l=Math.min(l,x);r=Math.max(r,x);t=Math.min(t,y);b=Math.max(b,y);count++;}}
    if(r<l||b<t||count<16)throw Error('未识别到足够的主体，请手动拖动选区。');
    const margin=Math.max(r-l+1,b-t+1)*.035;const x=clamp((l-margin)/scale,0,work.width-1),y=clamp((t-margin)/scale,0,work.height-1);return{x,y,w:Math.min(work.width-x,(r-l+1+margin*2)/scale),h:Math.min(work.height-y,(b-t+1+margin*2)/scale)};
  }
  $('trim').onclick=()=>{try{editCrop(item=>{item.crop=trimBounds();$('ratio').value='free';});status('已识别边界，请核对文字、阴影及主体是否完整，再微调选区。');}catch(e){status(e.message,true);}};
  $('reset').onclick=()=>editCrop(item=>item.crop=centerRect(ratio()));
  function transform(turn,flip){editCrop(item=>{item.rotation=(item.rotation+turn+4)%4;if(flip)item.flip=!item.flip;paintWork(originalImage,item);item.crop=centerRect(ratio());});}
  $('left').onclick=()=>transform(-1,false);$('right').onclick=()=>transform(1,false);$('flip').onclick=()=>transform(0,true);
  function historyMove(undo){const item=current();if(!item||busy)return;const from=undo?item.history:item.future,to=undo?item.future:item.history;if(!from.length)return;to.push(stateOf(item));Object.assign(item,from.pop());$('ratio').value=item.ratio;paintWork(originalImage,item);refresh();renderQueue();}
  $('undo').onclick=()=>historyMove(true);$('redo').onclick=()=>historyMove(false);
  function togglePreview(preview){previewMode=preview;$('crop-tab').setAttribute('aria-pressed',String(!preview));$('preview-tab').setAttribute('aria-pressed',String(preview));stage.style.cursor=preview?'default':'crosshair';draw();}
  $('crop-tab').onclick=()=>togglePreview(false);$('preview-tab').onclick=()=>togglePreview(true);
  for(const [id,key]of[['x','x'],['y','y'],['cw','w'],['ch','h']])$(id).addEventListener('change',()=>editCrop(item=>{const r={...item.crop,[key]:number(id,item.crop[key])};if(ratio()){if(key==='w')r.h=r.w/ratio();if(key==='h')r.w=r.h*ratio();}item.crop=fitRect(r,ratio());}));
  $('ratio').onchange=()=>editCrop(item=>{if(ratio())item.crop=constrainAspect(item.crop,ratio());});
  $('files').onchange=async e=>{await addSources([...e.target.files].map(file=>({file})));e.target.value='';};
  $('clear').onclick=()=>{if(busy)return;items.forEach(i=>{if(i.file)URL.revokeObjectURL(i.url);});items=[];clearWorkspace();renderQueue();};
  ['dragenter','dragover'].forEach(n=>$('dropzone').addEventListener(n,e=>{e.preventDefault();$('dropzone').classList.add('is-dragover');}));
  ['dragleave','drop'].forEach(n=>$('dropzone').addEventListener(n,e=>{e.preventDefault();$('dropzone').classList.remove('is-dragover');if(n==='drop')addSources([...e.dataTransfer.files].map(file=>({file})));}));
  function formatChanged(){const png=$('format').value==='image/png';$('quality').disabled=png;$('quality-value').textContent=png?'无损':number('quality',85)+'%';if($('format').value==='image/jpeg'&&$('background').value==='transparent'){$('background').value='#ffffff';status('JPEG 不支持透明，已改用白底。');}$('format-help').textContent=png?'PNG 使用无损编码，质量滑块不适用。导出不会保留 EXIF / GPS。':'照片会重新编码，不保留 EXIF / GPS；WebP 适合网站使用。';}
  $('preset').onchange=()=>{const match=$('preset').value.match(/^(\d+)x(\d+)/);if(match){$('width').value=match[1];$('height').value=match[2];if(ratio()&&current())editCrop(item=>item.crop=constrainAspect(item.crop,ratio()));}refresh();};
  ['width','height'].forEach(id=>$(id).addEventListener('change',()=>{$('preset').value='custom';if($('ratio').value==='output'&&current())editCrop(item=>item.crop=constrainAspect(item.crop,ratio()));refresh();}));
  ['fit','format','background','color','model','view','start','pattern','title','alt'].forEach(id=>$(id).addEventListener('input',()=>{if(id==='format'||id==='background')formatChanged();$('color').disabled=$('background').value!=='custom';refresh();}));
  ['padding','quality','threshold'].forEach(id=>$(id).addEventListener('input',()=>{if(id==='padding')$('padding-value').textContent=number(id,5)+'%';if(id==='quality')$('quality-value').textContent=number(id,85)+'%';if(id==='threshold')$('threshold-value').textContent=number(id,245);refresh();}));
  async function encoded(item){if(active!==item.id||!originalImage)await selectItem(item.id);if(!originalImage||active!==item.id)throw Error('图片尚未读取完成。');const s=settings(),canvas=document.createElement('canvas');renderOutput(canvas,s);const blob=await new Promise(resolve=>canvas.toBlob(resolve,s.mime,s.quality));canvas.width=1;canvas.height=1;if(!blob||blob.type!==s.mime)throw Error('浏览器不支持所选输出格式，请换 JPEG 或 PNG。');if(blob.size>20*1048576)throw Error('导出文件超过 20 MB，请降低尺寸或质量。');return{blob,...s};}
  function download(blob,name){const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);}
  async function api(action,data={}){const body=new FormData();body.set('action',action);body.set('nonce',config.nonce);Object.entries(data).forEach(([key,value])=>body.set(key,value));let response;try{response=await fetch(config.ajax,{method:'POST',credentials:'same-origin',body});}catch(e){throw Error('网络连接中断，请重试；已生成的原图不会被修改。');}let j;try{j=await response.json();}catch(e){throw Error('服务器未返回有效结果，请检查上传限制或刷新后台。');}if(!j.success)throw Error(j.data?.message||'操作失败，请刷新后台重试。');return j.data;}
  function addResult(message,link){const li=document.createElement('li');li.append(document.createTextNode(message));if(link){const a=document.createElement('a');a.href=link;a.target='_blank';a.rel='noopener';a.textContent=' 查看媒体';li.append(a);}$('results').prepend(li);}
  function setBusy(value){if(value&&!busy){disabledBeforeBusy=new Map([...root.querySelectorAll('input,select')].map(el=>[el,el.disabled]));disabledBeforeBusy.forEach((old,el)=>el.disabled=true);}else if(!value&&busy&&disabledBeforeBusy){disabledBeforeBusy.forEach((old,el)=>el.disabled=old);disabledBeforeBusy=null;}busy=value;refreshEnabled();}
  async function exportItems(all,save){
    if(busy||!items.length)return;const chosen=all?[...items]:[current()];if(!chosen[0])return;const previous=active,names=plannedNames(),archive=[];let ok=0,failed=0,total=0;setBusy(true);
    try{
      for(let n=0;n<chosen.length;n++){
        const item=chosen[n],index=items.indexOf(item),name=names[index];status('正在处理 '+(n+1)+' / '+chosen.length+'：'+item.name);
        try{
          const output=await encoded(item);
          if(save){if(!config.wordpress)throw Error('请在 WordPress 后台使用另存媒体库。');if(output.blob.size>config.maxUpload)throw Error('图片超过本站上传限制，请缩小尺寸或降低质量。');const data=await api('ianpe_save',{image:new File([output.blob],name,{type:output.blob.type}),title:template($('title').value,item,index)||name.replace(/\.[^.]+$/,''),alt:$('alt').value?template($('alt').value,item,index):item.alt,source:item.mediaId});addResult('已另存 #'+data.id+' · '+data.name+' · '+data.width+' × '+data.height+' · '+bytes(output.blob.size),data.edit);}
          else if(all){total+=output.blob.size;if(total>150*1048576)throw Error('ZIP 已达到 150 MB，请分批下载。');archive.push({name,blob:output.blob});}
          else{download(output.blob,name);addResult('已导出 '+name+' · '+output.width+' × '+output.height+' · '+bytes(output.blob.size));}
          ok++;
        }catch(e){failed++;addResult(item.name+'：'+e.message);}
      }
      if(archive.length){const zip=await makeZip(archive);download(zip,'ianproject-images.zip');addResult('已导出 ZIP · '+archive.length+' 张 · '+bytes(zip.size));}
      status('处理完成：成功 '+ok+' 张'+(failed?'，失败 '+failed+' 张，请查看下方记录。':'。')+(save?' 新图已进入媒体库，原图和产品引用保持不变。':''),failed>0);
    }catch(e){status(e.message,true);}finally{if(items.some(i=>i.id===previous))await selectItem(previous);setBusy(false);}
  }
  $('download').onclick=()=>exportItems(false,false);$('download-all').onclick=()=>exportItems(true,false);$('save').onclick=()=>exportItems(false,true);$('save-all').onclick=()=>exportItems(true,true);
  if(config.wordpress){$('media').hidden=false;$('save').hidden=false;$('save-all').hidden=false;$('media').onclick=()=>{if(busy||!window.wp?.media)return;const frame=wp.media({title:'选择要处理的原图',button:{text:'添加到编辑器'},library:{type:'image'},multiple:true});frame.on('open',()=>frame.content.mode('browse'));frame.on('select',async()=>{const selection=frame.state().get('selection').toJSON(),sources=[];setBusy(true);try{for(const a of selection){try{sources.push(await api('ianpe_source',{id:a.id}));}catch(e){addResult((a.filename||a.id)+'：'+e.message);}}}finally{setBusy(false);}await addSources(sources);});frame.open();};}
  // ZIP store mode: images are already compressed, so no library or server is needed.
  const crcTable=new Uint32Array(256);for(let i=0;i<256;i++){let c=i;for(let j=0;j<8;j++)c=c&1?0xedb88320^(c>>>1):c>>>1;crcTable[i]=c>>>0;}
  function crc32(data){let c=0xffffffff;for(const v of data)c=crcTable[(c^v)&255]^(c>>>8);return(c^0xffffffff)>>>0;}
  async function makeZip(entries){const chunks=[],directory=[],encoder=new TextEncoder();let offset=0;const now=new Date(),dosTime=(now.getHours()<<11)|(now.getMinutes()<<5)|(now.getSeconds()>>1),dosDate=((Math.max(1980,now.getFullYear())-1980)<<9)|((now.getMonth()+1)<<5)|now.getDate();for(const entry of entries){const name=encoder.encode(entry.name),data=new Uint8Array(await entry.blob.arrayBuffer()),crc=crc32(data),head=new Uint8Array(30+name.length),h=new DataView(head.buffer);h.setUint32(0,0x04034b50,true);h.setUint16(4,20,true);h.setUint16(6,0x800,true);h.setUint16(10,dosTime,true);h.setUint16(12,dosDate,true);h.setUint32(14,crc,true);h.setUint32(18,data.length,true);h.setUint32(22,data.length,true);h.setUint16(26,name.length,true);head.set(name,30);chunks.push(head,data);const central=new Uint8Array(46+name.length),c=new DataView(central.buffer);c.setUint32(0,0x02014b50,true);c.setUint16(4,20,true);c.setUint16(6,20,true);c.setUint16(8,0x800,true);c.setUint16(12,dosTime,true);c.setUint16(14,dosDate,true);c.setUint32(16,crc,true);c.setUint32(20,data.length,true);c.setUint32(24,data.length,true);c.setUint16(28,name.length,true);c.setUint32(42,offset,true);central.set(name,46);directory.push(central);offset+=head.length+data.length;}const centralSize=directory.reduce((n,c)=>n+c.length,0),end=new Uint8Array(22),e=new DataView(end.buffer);e.setUint32(0,0x06054b50,true);e.setUint16(8,entries.length,true);e.setUint16(10,entries.length,true);e.setUint32(12,centralSize,true);e.setUint32(16,offset,true);return new Blob([...chunks,...directory,end],{type:'application/zip'});}
  const detector=document.createElement('canvas');detector.width=detector.height=1;if(!detector.toDataURL('image/webp').startsWith('data:image/webp')){$('format').querySelector('[value="image/webp"]').disabled=true;$('format').value='image/jpeg';}
  new ResizeObserver(()=>{draw();}).observe($('dropzone'));
  formatChanged();renderQueue();clearWorkspace();
  if(config.wordpress&&config.initialAttachment){api('ianpe_source',{id:config.initialAttachment}).then(s=>addSources([s])).catch(e=>status(e.message,true));}
  if(!config.wordpress){$('empty').querySelector('p').textContent='从电脑选择 JPEG、PNG 或 WebP 图片';root.querySelector('.ipe-export>.ipe-small').textContent='多图共用输出尺寸、格式和命名规则；裁剪范围按每张图分别保留。导出新文件，不覆盖原图。';}
})();
