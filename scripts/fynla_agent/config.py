"""Configuration for the Fynla Agent SDK sidecar."""
import os

# API configuration
ANTHROPIC_API_KEY = os.environ.get('ANTHROPIC_API_KEY', '')
LARAVEL_API_URL = os.environ.get('LARAVEL_API_URL', 'http://127.0.0.1:8000')
AGENT_SECRET = os.environ.get('AGENT_INTERNAL_TOKEN', '')

# Model configuration
DEFAULT_MODEL = 'claude-haiku-4-5-20251001'
ADVANCED_MODEL = os.environ.get('ANTHROPIC_ADVANCED_CHAT_MODEL', 'claude-sonnet-4-6-20260320')

# Limits
MAX_TURNS = 10
MAX_TOKENS = 8192
