#ifndef MOD_AUDIO_STREAM_H
#define MOD_AUDIO_STREAM_H

#include <switch.h>
#include <speex/speex_resampler.h>

#define MY_BUG_NAME "audio_stream"
#define MAX_SESSION_ID (256)
#define MAX_WS_URI (4096)
#define MAX_METADATA_LEN (8192)

#define EVENT_CONNECT           "mod_audio_stream::connect"
#define EVENT_DISCONNECT        "mod_audio_stream::disconnect"
#define EVENT_ERROR             "mod_audio_stream::error"
#define EVENT_JSON              "mod_audio_stream::json"
#define EVENT_PLAY              "mod_audio_stream::play"

/* Audio format types */
#define AUDIO_FORMAT_L16    0   /* Linear PCM 16-bit (default) */
#define AUDIO_FORMAT_PCMU   1   /* G.711 µ-law */
#define AUDIO_FORMAT_PCMA   2   /* G.711 A-law */

typedef void (*responseHandler_t)(switch_core_session_t* session, const char* eventName, const char* json);

struct private_data {
    switch_mutex_t *mutex;
    char sessionId[MAX_SESSION_ID];
    SpeexResamplerState *resampler;
    responseHandler_t responseHandler;
    void *pAudioStreamer;
    char ws_uri[MAX_WS_URI];
    int sampling;
    int channels;
    int audio_paused:1;
    int close_requested:1;
    int cleanup_started:1;
    char initialMetadata[8192];
    switch_buffer_t *sbuffer;
    int rtp_packets;
    int audio_format;           /* AUDIO_FORMAT_L16, AUDIO_FORMAT_PCMU, AUDIO_FORMAT_PCMA */
    switch_codec_t write_codec; /* Codec for encoding L16 to PCMU/PCMA */
    int codec_initialized:1;    /* Flag indicating if codec is initialized */
};

typedef struct private_data private_t;

enum notifyEvent_t {
    CONNECT_SUCCESS,
    CONNECT_ERROR,
    CONNECTION_DROPPED,
    MESSAGE
};

#endif //MOD_AUDIO_STREAM_H
