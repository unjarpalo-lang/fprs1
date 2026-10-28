(() => {
  const script = document.currentScript;
  const endpoint = script?.dataset.endpoint;
  if (!endpoint) return;
  const nodes = [...document.querySelectorAll('[data-package-rating-id]')];
  if (!nodes.length) return;
  let refreshing = false;
  const refresh = async () => {
    if (refreshing || document.hidden) return;
    refreshing = true;
    try {
      const response = await fetch(endpoint, {credentials: 'same-origin', cache: 'no-store'});
      if (!response.ok) return;
      const {ratings} = await response.json();
      for (const node of nodes) {
        const rating = ratings?.[node.dataset.packageRatingId];
        if (!rating) continue;
        const hasRecordedReviews = node.dataset.hasRecordedReviews === '1';
        node.style.display = !rating.count && hasRecordedReviews ? 'none' : '';
        node.textContent = rating.count ? `\u2605 ${Number(rating.average).toFixed(1)}/5 (${rating.count} review${rating.count === 1 ? '' : 's'})` : (hasRecordedReviews ? '' : 'No reviews yet');
      }
    } catch { /* Keep the server-rendered rating when offline. */ }
    finally { refreshing = false; }
  };
  setInterval(refresh, 10000);
  window.addEventListener('focus', refresh);
  document.addEventListener('visibilitychange', refresh);
  if ('BroadcastChannel' in window) {
    const channel = new BroadcastChannel('fprs-package-ratings');
    channel.onmessage = refresh;
    if (script.dataset.reviewedPackage) channel.postMessage(script.dataset.reviewedPackage);
  }
})();
