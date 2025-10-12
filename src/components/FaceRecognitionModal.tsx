import { useEffect, useRef, useState } from "react";
import { X, Camera } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

interface FaceRecognitionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onFaceRecognized: (recognized: boolean) => void;
}

const FaceRecognitionModal = ({
  isOpen,
  onClose,
  onFaceRecognized,
}: FaceRecognitionModalProps) => {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [isScanning, setIsScanning] = useState(false);
  const [stream, setStream] = useState<MediaStream | null>(null);

  useEffect(() => {
    if (isOpen) {
      startCamera();
    }
    return () => {
      stopCamera();
    };
  }, [isOpen]);

  const startCamera = async () => {
    try {
      const mediaStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "user" },
      });
      setStream(mediaStream);
      if (videoRef.current) {
        videoRef.current.srcObject = mediaStream;
      }
    } catch (error) {
      console.error("Error accessing camera:", error);
    }
  };

  const stopCamera = () => {
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      setStream(null);
    }
  };

  const handleScan = () => {
    setIsScanning(true);
    // Simulate face recognition - in production, use face-api.js
    setTimeout(() => {
      setIsScanning(false);
      stopCamera();
      onFaceRecognized(true);
    }, 2000);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <Card className="w-full max-w-2xl p-6 animate-scale-in">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-2xl font-bold text-foreground">Face Recognition</h2>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              stopCamera();
              onClose();
            }}
          >
            <X className="w-5 h-5" />
          </Button>
        </div>

        <div className="relative aspect-video bg-black rounded-lg overflow-hidden mb-4">
          <video
            ref={videoRef}
            autoPlay
            playsInline
            muted
            className="w-full h-full object-cover"
          />
          
          {/* Scanning Overlay */}
          {isScanning && (
            <div className="absolute inset-0 flex items-center justify-center bg-primary/20">
              <div className="w-64 h-64 border-4 border-primary rounded-full animate-pulse-soft" />
            </div>
          )}

          {/* Face Detection Frame */}
          <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div className="w-64 h-80 border-4 border-primary rounded-2xl" />
          </div>
        </div>

        <div className="space-y-3">
          <p className="text-center text-muted-foreground">
            Position your face within the frame
          </p>
          <Button
            size="lg"
            className="w-full h-14 text-lg"
            onClick={handleScan}
            disabled={isScanning}
          >
            <Camera className="w-5 h-5 mr-2" />
            {isScanning ? "Scanning..." : "Capture & Verify"}
          </Button>
        </div>
      </Card>
    </div>
  );
};

export default FaceRecognitionModal;
