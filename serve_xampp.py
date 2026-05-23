import os

os.environ.setdefault("APPLICATION_ROOT", "/biffi-olimpiadas")

from app import app  # noqa: E402


def main():
    host = os.getenv("FLASK_RUN_HOST", "127.0.0.1")
    port = int(os.getenv("FLASK_RUN_PORT", "5050"))
    try:
        from waitress import serve

        serve(app, host=host, port=port, threads=8)
    except Exception:
        app.run(host=host, port=port, debug=False)


if __name__ == "__main__":
    main()
