'use strict';

import { AudioRecord } from './AudioRecord.js';
import { recordingProcessorEncapsulation } from './RecordingProcessor.js';

/**
 * @class LinguaRecorder
 * Main class of the library, used to control the recording process.
 */
export class LinguaRecorder {

	constructor( config ) {
		this.config = {
			autoStart: false,
			autoStop: false,
			bufferSize: 4096,
			timeLimit: 0,
			onSaturate: 'none',
			saturationThreshold: 0.99,
			startThreshold: 0.1,
			stopThreshold: 0.05,
			stopDuration: 0.3,
			marginBefore: 0.25,
			marginAfter: 0.25,
			minDuration: 0.15,
		};

		this._eventHandlers = {
			ready: [],
			started: [],
			recording: [],
			paused: [],
			stopped: [],
			canceled: [],
		};

		if ( typeof config === 'object' ) {
			for ( let key in config ) {
				if ( config.hasOwnProperty( key ) ) {
					this.config[ key ] = config[ key ];
				}
			}
		}

		this.audioContext = new ( window.AudioContext || window.webkitAudioContext )();
		
		// --- FIX: Pass sampleRate to config so Processor can calculate duration ---
		this.config.sampleRate = this.audioContext.sampleRate;

		this.audioContext.onstatechange = () => {
			if ( this.audioContext.state === 'running' ) {
				this._fire( 'ready' );
			}
		};

		this.stream = null;
		this.sourceNode = null;
		this.workletNode = null;
		this.state = 'stop';
	}

	on( event, handler ) {
		if ( event in this._eventHandlers ) {
			this._eventHandlers[ event ].push( handler );
		}
		return this;
	}

	off( event, handler ) {
		if ( event in this._eventHandlers ) {
			var index = this._eventHandlers[ event ].indexOf( handler );
			if ( index !== -1 ) {
				this._eventHandlers[ event ].splice( index, 1 );
			}
		}
		return this;
	}

	_fire( event, data ) {
		if ( event in this._eventHandlers ) {
			for ( var i = 0; i < this._eventHandlers[ event ].length; i++ ) {
				this._eventHandlers[ event ][ i ]( data );
			}
		}
	}

	start() {
		if ( this.state !== 'stop' ) {
			return;
		}

		if ( this.audioContext.state === 'suspended' ) {
			this.audioContext.resume();
		}

		if ( this.stream ) {
			this._initWorklet();
		}
		else {
			navigator.mediaDevices.getUserMedia( { audio: true, video: false } )
				.then( ( stream ) => {
					this.stream = stream;
					this.sourceNode = this.audioContext.createMediaStreamSource( stream );
					this._initWorklet();
				} );
		}
	}

	pause() {
		if ( this.state === 'recording' && this.workletNode !== null ) {
			this.workletNode.port.postMessage( { message: 'pause' } );
			this.state = 'paused';
			this._fire( 'paused' );
		}
	}

	stop( cancelRecord ) {
		if ( this.state !== 'stop' && this.workletNode !== null ) {
			if ( cancelRecord ) {
				this.workletNode.port.postMessage( { message: 'cancel' } );
			}
			else {
				this.workletNode.port.postMessage( { message: 'stop' } );
			}
		}
	}

	cancel() {
		this.stop( true );
	}

	toggle() {
		if ( this.state === 'recording' || this.state === 'listening' ) {
			this.stop();
		}
		else {
			this.start();
		}
	}

	close() {
		if ( this.audioContext ) {
			this.audioContext.close();
		}
		this.stream = null;
		this.sourceNode = null;
		this.workletNode = null;
	}

	_initWorklet() {
		const blob = new Blob( [ `(${recordingProcessorEncapsulation.toString()})()` ], { type: 'application/javascript' } );
		const url = URL.createObjectURL( blob );

		this.audioContext.audioWorklet.addModule( url )
			.then( () => {
				this.workletNode = new AudioWorkletNode( this.audioContext, 'recording-processor', {
					processorOptions: this.config,
				} );

				this.workletNode.port.onmessage = ( event ) => {
					switch ( event.data.message ) {
						case 'started':
							this.state = 'recording';
							this._fire( 'started' );
							break;
						case 'paused':
							this.state = 'paused';
							this._fire( 'paused' );
							break;
						case 'stopped':
							this.state = 'stop';
							this._fire( 'stopped', new AudioRecord( event.data.record, this.config.sampleRate ) );
							this._disconnect();
							break;
						case 'canceled':
							this.state = 'stop';
							this._fire( 'canceled', event.data.reason );
							this._disconnect();
							break;
						case 'recording':
							this._fire( 'recording', new AudioRecord( event.data.samples, this.config.sampleRate ) );
							break;
						case 'listening':
							this.state = 'listening';
							this._fire( 'listening', event.data.samples );
							break;
						case 'saturated':
							this._fire( 'saturated' );
							break;
					}
				};

				this.sourceNode.connect( this.workletNode );
				// --- FIX: REMOVED connection to destination to prevent feedback loop ---
				// this.workletNode.connect( this.audioContext.destination );
				
				this.workletNode.port.postMessage( { message: 'start' } );
			} )
			.catch( ( e ) => {
				console.error( 'Error while initializing the AudioWorklet', e );
			} );
	}

	_disconnect() {
		if ( this.workletNode ) {
			this.workletNode.port.postMessage( { message: 'close' } );
			this.workletNode.disconnect();
			this.workletNode = null;
		}
		if ( this.sourceNode ) {
			this.sourceNode.disconnect();
		}
	}
}