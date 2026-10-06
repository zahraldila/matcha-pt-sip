// Custom Flutter Web bootstrap configuration
// Mengatasi masalah TypeError: Failed to fetch dari gstatic CDN dengan fallback CanvasKit lokal.

{{flutter_js}}
{{flutter_build_config}}

_flutter.loader.load({
  config: {
    // Jika CanvasKit disajikan lokal (misal via build --no-web-resources-cdn atau folder /canvaskit/)
    // loader akan mengambil dari base path lokal alih-alih memaksa ke www.gstatic.com
    canvasKitBaseUrl: (_flutter.buildConfig && _flutter.buildConfig.useLocalCanvasKit)
      ? "canvaskit/"
      : undefined,
  },
  serviceWorkerSettings: {
    serviceWorkerVersion: {{flutter_service_worker_version}}
  }
});
