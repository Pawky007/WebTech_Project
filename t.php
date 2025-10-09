<?php
echo 'KEY FROM getenv: ' . (getenv('OPENROUTER_API_KEY') ? '✅ found' : '❌ missing');
