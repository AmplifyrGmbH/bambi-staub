#!/usr/bin/env python3
"""Lokaler Dev-Server mit Clean-URL-Unterstützung (wie .htaccess auf Apache)."""
from http.server import HTTPServer, SimpleHTTPRequestHandler
import os

class CleanURLHandler(SimpleHTTPRequestHandler):
    def translate_path(self, path):
        # Entferne Query-String und Fragment
        path = path.split('?')[0].split('#')[0]
        result = super().translate_path(path)
        # Wenn Datei nicht gefunden und kein Suffix → .html anhängen
        if not os.path.exists(result) and not os.path.splitext(result)[1]:
            html = result.rstrip('/') + '.html'
            if os.path.exists(html):
                return html
        return result

    def log_message(self, format, *args):
        print(f"  {self.path}")

if __name__ == '__main__':
    port = 8080
    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    print(f"Server läuft auf http://localhost:{port}")
    HTTPServer(('', port), CleanURLHandler).serve_forever()
