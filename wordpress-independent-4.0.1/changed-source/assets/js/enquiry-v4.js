/* The visitor reviews and sends the message in their own email / WhatsApp. */
(() => {
 'use strict';
 const form=document.getElementById('quote-builder-form'),config=window.IAN_ENQUIRY;
 if(!form||!config)return;
 const $=id=>document.getElementById('request-'+id),feedback=$('feedback'),preview=$('preview'),wrap=$('preview-wrap'),manual=$('manual');
 const subject='IanProject project request';
 function prepare(){
  if(!form.reportValidity())return null;
  const website=$('website').value.trim();
  if(website){try{const u=new URL(/^https?:\/\//i.test(website)?website:'https://'+website);if(!['http:','https:'].includes(u.protocol)||!u.hostname.includes('.'))throw Error();$('website').setCustomValidity('');}catch(e){$('website').setCustomValidity('Enter a valid company website or leave it blank.');$('website').reportValidity();return null;}}
  const body='IanProject project request\n\nName: '+$('name').value.trim()+'\nCompany website: '+(website||'Not provided')+'\nCountry: '+$('country').value.trim()+'\nTrade terms: '+$('terms').value+'\n\nRequirements:\n'+$('requirements').value.trim()+'\n\nPlease confirm the specifications, final price, packing and delivery.';
  preview.value=body;wrap.hidden=false;manual.hidden=true;manual.replaceChildren();return body;
 }
 $('website').addEventListener('input',()=>$('website').setCustomValidity(''));
 function launch(url,newTab){const a=document.createElement('a');a.href=url;if(newTab){a.target='_blank';a.rel='noopener noreferrer';}document.body.append(a);a.click();a.remove();}
 function longMessage(url,label){wrap.open=true;manual.hidden=false;const p=document.createElement('p');p.className='small';p.textContent='This request is long. Copy it below, open '+label+', then paste it into your message.';const a=document.createElement('a');a.href=url;a.textContent='Open '+label+' and paste your request';a.target='_blank';a.rel='noopener noreferrer';manual.append(p,a);feedback.textContent='Your complete request is ready to copy. No message has been sent.';preview.focus();preview.select();}
 function email(){const body=prepare();if(!body)return;const short='mailto:'+config.email+'?subject='+encodeURIComponent(subject),url=short+'&body='+encodeURIComponent(body);if(url.length>1900){longMessage(short,'your email app');return;}launch(url,false);feedback.textContent='Review your draft in your email app and press Send there. If no email app opens, copy the request and email it to '+config.email+'.';}
 function whatsapp(){const body=prepare();if(!body)return;let url;try{url=new URL(config.whatsapp);if(url.protocol!=='https:')throw Error();}catch(e){feedback.textContent='WhatsApp is unavailable. Please use email or copy your request.';return;}const base=url.href;url.searchParams.set('text',body);if(url.href.length>7000){longMessage(base,'WhatsApp');return;}launch(url.href,true);feedback.textContent='Review your message in WhatsApp and press Send there. No message is sent automatically.';}
 async function copy(){const body=prepare();if(!body)return;try{await navigator.clipboard.writeText(body);feedback.textContent='Request copied. Paste it into your email or WhatsApp and send it there.';}catch(e){wrap.open=true;preview.focus();preview.select();feedback.textContent='Select and copy the prepared request, then paste it into your email or WhatsApp.';}}
 $('email').disabled=false;$('whatsapp').disabled=false;$('copy').disabled=false;
 $('email').addEventListener('click',email);$('whatsapp').addEventListener('click',whatsapp);$('copy').addEventListener('click',copy);
 form.addEventListener('submit',event=>{event.preventDefault();email();});
})();
