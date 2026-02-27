import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';
import 'package:webview_flutter_wkwebview/webview_flutter_wkwebview.dart';

void main() {
  runApp(const AbsenPanhaApp());
}

class AbsenPanhaApp extends StatelessWidget {
  const AbsenPanhaApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Absen Panha',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0B4A86)),
        useMaterial3: true,
      ),
      home: const SplashPage(),
    );
  }
}

class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  @override
  void initState() {
    super.initState();
    Timer(const Duration(milliseconds: 1400), () {
      if (!mounted) {
        return;
      }
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(builder: (_) => const HomeWebViewPage()),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: SafeArea(
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(Icons.language_rounded, size: 72, color: Color(0xFF0B4A86)),
              SizedBox(height: 16),
              Text(
                'Absen Panha',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.4,
                ),
              ),
              SizedBox(height: 16),
              SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(strokeWidth: 2.6),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class HomeWebViewPage extends StatefulWidget {
  const HomeWebViewPage({super.key});

  @override
  State<HomeWebViewPage> createState() => _HomeWebViewPageState();
}

class _HomeWebViewPageState extends State<HomeWebViewPage>
    with WidgetsBindingObserver {
  static const String _targetUrl = 'https://absenpanha.com';
  static const Duration _retryInterval = Duration(seconds: 3);
  static const int _maxRetryAttempt = 30;

  late final WebViewController _controller;
  bool _isPageLoading = true;
  int _progressValue = 0;
  bool _hasMainFrameError = false;
  bool _startupDialogShown = false;
  int _retryCount = 0;
  Timer? _retryTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    PlatformWebViewControllerCreationParams params =
        const PlatformWebViewControllerCreationParams();

    if (WebViewPlatform.instance is WebKitWebViewPlatform) {
      params = WebKitWebViewControllerCreationParams(
        allowsInlineMediaPlayback: true,
        mediaTypesRequiringUserAction: const <PlaybackMediaTypes>{},
      );
    }

    late final WebViewController controller;
    controller = WebViewController.fromPlatformCreationParams(
      params,
      onPermissionRequest: _handleWebViewPermissionRequest,
    )
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (NavigationRequest request) {
            final Uri? uri = Uri.tryParse(request.url);
            if (uri == null) {
              return NavigationDecision.prevent;
            }

            final String scheme = uri.scheme.toLowerCase();
            if (scheme == 'https' ||
                scheme == 'about' ||
                scheme == 'data' ||
                scheme == 'javascript' ||
                scheme == 'file' ||
                scheme == 'blob') {
              return NavigationDecision.navigate;
            }

            if (scheme == 'http') {
              final Uri httpsUri = uri.replace(
                scheme: 'https',
                port: uri.hasPort && uri.port == 80 ? 443 : uri.port,
              );
              controller.loadRequest(httpsUri);
            }
            return NavigationDecision.prevent;
          },
          onProgress: (int progress) {
            if (!mounted) {
              return;
            }
            setState(() {
              _progressValue = progress;
              _isPageLoading = progress < 100;
            });
          },
          onPageStarted: (_) {
            if (!mounted) {
              return;
            }
            setState(() {
              _hasMainFrameError = false;
              _isPageLoading = true;
            });
          },
          onPageFinished: (_) {
            if (!mounted) {
              return;
            }
            setState(() {
              _progressValue = 100;
              _hasMainFrameError = false;
              _isPageLoading = false;
            });
            _stopRetryLoop();
          },
          onWebResourceError: (WebResourceError error) {
            if (error.isForMainFrame != true) {
              return;
            }
            if (!mounted) {
              return;
            }
            setState(() {
              _hasMainFrameError = true;
              _isPageLoading = false;
            });
            _startRetryLoop();
          },
        ),
      )
      ..loadRequest(Uri.parse(_targetUrl));

    if (Platform.isAndroid) {
      final AndroidWebViewController androidController =
          controller.platform as AndroidWebViewController;
      androidController.setMediaPlaybackRequiresUserGesture(false);
      unawaited(
        androidController.setGeolocationPermissionsPromptCallbacks(
          onShowPrompt: (GeolocationPermissionsRequestParams _) async {
            final bool granted = await _ensureLocationPermission();
            return GeolocationPermissionsResponse(
              allow: granted,
              retain: granted,
            );
          },
        ),
      );
    }

    _controller = controller;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_showStartupDataNoticeAndReload());
    });
  }

  Future<void> _handleWebViewPermissionRequest(
    WebViewPermissionRequest request,
  ) async {
    final bool granted = await _requestRuntimePermissions(request.types);
    if (granted) {
      await request.grant();
      return;
    }
    await request.deny();
  }

  Future<bool> _requestRuntimePermissions(
    Set<WebViewPermissionResourceType> types,
  ) async {
    if (!Platform.isAndroid && !Platform.isIOS) {
      return true;
    }

    final List<Permission> neededPermissions = <Permission>[];
    if (types.contains(WebViewPermissionResourceType.camera)) {
      neededPermissions.add(Permission.camera);
    }
    if (types.contains(WebViewPermissionResourceType.microphone)) {
      neededPermissions.add(Permission.microphone);
    }

    if (neededPermissions.isEmpty) {
      return true;
    }

    final Map<Permission, PermissionStatus> statuses =
        await neededPermissions.request();
    return statuses.values.every(
        (PermissionStatus status) => status.isGranted || status.isLimited);
  }

  Future<bool> _ensureLocationPermission() async {
    if (!Platform.isAndroid && !Platform.isIOS) {
      return true;
    }

    final PermissionStatus current = await Permission.locationWhenInUse.status;
    if (current.isGranted || current.isLimited) {
      return true;
    }

    final PermissionStatus requested =
        await Permission.locationWhenInUse.request();
    return requested.isGranted || requested.isLimited;
  }

  bool get _isMobilePlatform => Platform.isAndroid || Platform.isIOS;

  Future<void> _showStartupDataNoticeAndReload() async {
    if (!_isMobilePlatform || _startupDialogShown || !mounted) {
      return;
    }
    _startupDialogShown = true;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Info Koneksi'),
          content: const Text(
            'wajib menggunakan data untuk melakukan absen untuk sementara waktu karna baru ada error jika mengakses dengan wifi kantor, harap tutup dan buka aplikasi lagi jika sudah mengganti ke data. jika sudah klik oke',
          ),
          actions: <Widget>[
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Oke'),
            ),
          ],
        );
      },
    );

    if (!mounted) {
      return;
    }
    _retryCount = 0;
    await _controller.loadRequest(Uri.parse(_targetUrl));
    _startRetryLoop();
  }

  void _startRetryLoop() {
    if (_retryTimer?.isActive ?? false) {
      return;
    }

    _retryTimer = Timer.periodic(_retryInterval, (Timer timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }

      if (!_hasMainFrameError && !_isPageLoading) {
        timer.cancel();
        return;
      }

      if (_retryCount >= _maxRetryAttempt) {
        timer.cancel();
        return;
      }

      _retryCount += 1;
      unawaited(_controller.loadRequest(Uri.parse(_targetUrl)));
    });
  }

  void _stopRetryLoop() {
    _retryTimer?.cancel();
    _retryTimer = null;
    _retryCount = 0;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _hasMainFrameError) {
      _retryCount = 0;
      unawaited(_controller.loadRequest(Uri.parse(_targetUrl)));
      _startRetryLoop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stopRetryLoop();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, Object? result) async {
        if (didPop) {
          return;
        }

        final bool canGoBack = await _controller.canGoBack();
        if (canGoBack) {
          await _controller.goBack();
          return;
        }

        if (context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        body: SafeArea(
          child: Stack(
            children: <Widget>[
              WebViewWidget(controller: _controller),
              if (_isPageLoading)
                Positioned.fill(
                  child: ColoredBox(
                    color: Colors.black.withValues(alpha: 0.08),
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          const CircularProgressIndicator(),
                          const SizedBox(height: 12),
                          Text('Memuat... $_progressValue%'),
                        ],
                      ),
                    ),
                  ),
                ),
              if (_hasMainFrameError && !_isPageLoading)
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 18,
                  child: Card(
                    elevation: 4,
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: <Widget>[
                          const Expanded(
                            child: Text(
                              'Koneksi gagal. Aplikasi akan mencoba memuat ulang otomatis.',
                            ),
                          ),
                          TextButton(
                            onPressed: () {
                              _retryCount = 0;
                              unawaited(_controller.loadRequest(Uri.parse(_targetUrl)));
                              _startRetryLoop();
                            },
                            child: const Text('Muat Ulang'),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
