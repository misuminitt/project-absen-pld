import 'package:flutter_test/flutter_test.dart';

import 'package:absenpanha/main.dart';

void main() {
  testWidgets('App shows splash title', (WidgetTester tester) async {
    await tester.pumpWidget(const AbsenPanhaApp());
    expect(find.text('Absen Panha'), findsOneWidget);
  });
}
