/* مُركّب صورة الباج على canvas — للتنزيل والطباعة الموثوقة */
(function () {
  'use strict';
  var DPI = 300;
  function mm2px(mm) { return Math.round(mm / 25.4 * DPI); }

  function fieldValue(el, data, consts) {
    var f = el.field;
    if (f === 'custom') return el.text || '';
    if (f === 'site') return consts.site || '';
    if (f === 'edition') return consts.edition || '';
    if (f === 'dates') return consts.dates || '';
    if (f === 'gender') return data.gender === 'male' ? 'ذكر' : (data.gender === 'female' ? 'أنثى' : '');
    if (f === 'phone') return data.phone ? ('+' + data.phone) : '';
    return data[f] != null ? String(data[f]) : '';
  }

  function qrDataURL(text, color, sizePx) {
    var holder = document.createElement('div');
    holder.style.position = 'absolute'; holder.style.left = '-9999px';
    document.body.appendChild(holder);
    try {
      new QRCode(holder, { text: text, width: sizePx, height: sizePx, colorDark: color, colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    } catch (e) {}
    var img = holder.querySelector('img'), cv = holder.querySelector('canvas');
    var url = cv ? cv.toDataURL('image/png') : (img ? img.src : '');
    document.body.removeChild(holder);
    return url;
  }

  /* يرسم الباج على canvas ويستدعي cb(canvas) */
  window.renderBadgeCanvas = function (tpl, data, consts, cb) {
    var W = mm2px(tpl.w), H = mm2px(tpl.h);
    var canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    var ctx = canvas.getContext('2d');

    function drawElements() {
      (tpl.elements || []).forEach(function (el) {
        var cx = el.x / 100 * W, cy = el.y / 100 * H;
        if (el.type === 'qr') {
          var sz = mm2px(el.size);
          var url = qrDataURL(data.qr || data.code, el.color || '#000', Math.min(600, sz));
          if (url) {
            var im = new Image();
            im.onload = function () { ctx.drawImage(im, cx - sz / 2, cy - sz / 2, sz, sz); };
            im.src = url;
          }
        } else {
          var fpx = mm2px(el.size);
          ctx.font = (el.bold ? '700 ' : '400 ') + fpx + "px 'IBM Plex Sans Arabic', sans-serif";
          ctx.fillStyle = el.color || '#000';
          ctx.textAlign = el.align || 'center';
          ctx.textBaseline = 'middle';
          ctx.direction = 'rtl';
          ctx.fillText(fieldValue(el, data, consts), cx, cy);
        }
      });
      // انتظار تحميل صور QR ثم الإرجاع
      setTimeout(function () { cb(canvas); }, 260);
    }

    // الخلفية
    if (tpl.bg) {
      var bg = new Image();
      bg.crossOrigin = 'anonymous';
      bg.onload = function () { ctx.drawImage(bg, 0, 0, W, H); drawElements(); };
      bg.onerror = function () { ctx.fillStyle = tpl.bg_color || '#fff'; ctx.fillRect(0, 0, W, H); drawElements(); };
      bg.src = tpl.bgUrl || '';
    } else {
      ctx.fillStyle = tpl.bg_color || '#fff';
      ctx.fillRect(0, 0, W, H);
      drawElements();
    }
  };
})();
