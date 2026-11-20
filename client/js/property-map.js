// The controller includes this small module only when a property has coordinates.
// Import the map renderer only when the map approaches the viewport.
const nodes = document.querySelectorAll('[data-reapit-map]');

async function renderMap(node) {
  const latitude = Number(node.dataset.latitude);
  const longitude = Number(node.dataset.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)
      || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
    return;
  }

  try {
    const { Map, Marker, NavigationControl } = await import(
      'https://cdn.jsdelivr.net/npm/maplibre-gl@6.16.0/dist/maplibre-gl.mjs'
    );
    const map = new Map({
      container: node,
      style: node.dataset.styleUrl || 'https://tiles.openfreemap.org/styles/liberty',
      center: [longitude, latitude],
      zoom: 15,
      scrollZoom: false,
      attributionControl: true,
    });
    new Marker().setLngLat([longitude, latitude]).addTo(map);
    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
  } catch (error) {
    node.textContent = 'The location map could not be loaded.';
    console.error('Unable to initialise Reapit property map', error);
  }
}

if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue;
      observer.unobserve(entry.target);
      void renderMap(entry.target);
    }
  }, { rootMargin: '400px 0px' });
  nodes.forEach((node) => observer.observe(node));
} else {
  nodes.forEach((node) => { void renderMap(node); });
}
