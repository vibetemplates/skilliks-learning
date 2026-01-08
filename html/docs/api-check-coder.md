# /api/check-coder Endpoint Documentation

## Purpose

Check the status of a running Claude Code process and retrieve its output.

## Endpoint

`POST /api/check-coder`

## Authentication

Required header:
```
X-API-Key: your-api-key-here
```

## Request Body

```json
{
  "pid": 12345,
  "tempFile": "/tmp/tmp.eRNpW5ijst"
}
```

### Parameters:
- **pid** (required, number): Process ID of the running Claude Code instance
- **tempFile** or **outfile** (required, string): Path to the temporary output file
  - Use either `tempFile` or `outfile` - both are supported for backward compatibility

## Response Format

```json
{
  "status": "running" | "done" | "unknown",
  "response": "full output when done",
  "response_so_far": "partial output when running",
  "bytes": 1234,
  "error": "error message if any"
}
```

## Example Usage

### Check a running process:
```bash
curl -X POST https://your-domain.com/api/check-coder \
    -H "Content-Type: application/json" \
    -H "X-API-Key: sk-your-api-key" \
    -d '{"pid":82559,"tempFile":"/tmp/tmp.eRNpW5ijst"}'
```

### Response when running:
```json
{
  "status": "running",
  "response_so_far": "Current output from Claude Code...",
  "bytes": 512
}
```

### Response when completed:
```json
{
  "status": "done",
  "response": "Complete output from Claude Code execution",
  "bytes": 2048
}
```

### Response when process not found:
```json
{
  "status": "unknown",
  "error": "output file not found"
}
```

## Integration Notes

1. **Getting the PID and tempFile**: These values are returned when you initially call `/api/run-coder`:
   ```json
   {
     "pid": 82559,
     "tempFile": "/tmp/tmp.eRNpW5ijst"
   }
   ```

2. **Polling**: You can poll this endpoint to monitor long-running Claude Code executions:
   - Poll every 2-5 seconds
   - Check status field:
     - `"running"` - keep polling
     - `"done"` - execution complete
     - `"unknown"` - process may have terminated

3. **Error Handling**:
   - HTTP 500 with `{"error": "check-coder.sh script not found"}` - server misconfiguration
   - HTTP 400 with `{"error": "Either outfile or tempFile is required"}` - missing parameter

## Implementation in Project

This endpoint is used in the sprint management system to:
- Monitor the status of development prompts sent to Claude Code
- Retrieve partial output while prompts are being processed
- Update the database when prompts complete or fail
- Display real-time progress in the sprint dashboard

### Related Files:
- `/classes/DevSystemAPI.php` - Contains the checkCoderStatus method
- `/htmx/check-prompt-status.php` - HTMX endpoint for UI updates
- `/sprint-dashboard.php` - Displays prompt status and results