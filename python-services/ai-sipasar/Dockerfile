FROM python:3.13-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app

COPY pyproject.toml ./
COPY src ./src
COPY data ./data
COPY migrations ./migrations
COPY alembic.ini ./

RUN pip install .

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD python -c "import httpx; httpx.get('http://localhost:8000/api/v1/health/live').raise_for_status()"

CMD ["sh", "-c", "alembic upgrade head && uvicorn main:create_app --factory --app-dir src --host 0.0.0.0 --port 8000"]
