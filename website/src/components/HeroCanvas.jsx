import { Suspense, useRef } from 'react';
import { Canvas, useFrame } from '@react-three/fiber';
import { Float, Torus, Icosahedron, OrbitControls, Stars } from '@react-three/drei';
import * as THREE from 'three';

/* Rotating architectural wireframe geometry */
function ArchFrame({ position, rotation, color = '#f59e0b', scale = 1 }) {
  const meshRef = useRef();
  useFrame(({ clock }) => {
    if (meshRef.current) {
      meshRef.current.rotation.x = clock.getElapsedTime() * 0.15;
      meshRef.current.rotation.y = clock.getElapsedTime() * 0.22;
    }
  });
  return (
    <Float speed={2} rotationIntensity={0.4} floatIntensity={0.6}>
      <mesh ref={meshRef} position={position} rotation={rotation} scale={scale}>
        <icosahedronGeometry args={[1.2, 1]} />
        <meshStandardMaterial
          color={color}
          wireframe
          emissive={color}
          emissiveIntensity={0.15}
          transparent
          opacity={0.55}
        />
      </mesh>
    </Float>
  );
}

function TorusRing({ position, scale = 1, speed = 1 }) {
  const ref = useRef();
  useFrame(({ clock }) => {
    if (ref.current) {
      ref.current.rotation.x = clock.getElapsedTime() * 0.3 * speed;
      ref.current.rotation.z = clock.getElapsedTime() * 0.2 * speed;
    }
  });
  return (
    <Float speed={1.5} rotationIntensity={0.3} floatIntensity={0.4}>
      <mesh ref={ref} position={position} scale={scale}>
        <torusGeometry args={[1, 0.04, 16, 80]} />
        <meshStandardMaterial
          color="#f59e0b"
          emissive="#f59e0b"
          emissiveIntensity={0.4}
          transparent
          opacity={0.4}
        />
      </mesh>
    </Float>
  );
}

function GridFloor() {
  const count = 20;
  const lines = [];
  for (let i = -count; i <= count; i++) {
    const opacity = Math.max(0.03, 0.12 - Math.abs(i) * 0.005);
    lines.push(
      <mesh key={`h${i}`} position={[0, -3, i * 0.8]}>
        <boxGeometry args={[count * 1.6, 0.01, 0.01]} />
        <meshBasicMaterial color="#f59e0b" transparent opacity={opacity} />
      </mesh>
    );
    lines.push(
      <mesh key={`v${i}`} position={[i * 0.8, -3, 0]}>
        <boxGeometry args={[0.01, 0.01, count * 1.6]} />
        <meshBasicMaterial color="#f59e0b" transparent opacity={opacity} />
      </mesh>
    );
  }
  return <>{lines}</>;
}

export default function HeroCanvas() {
  return (
    <div className="hero-canvas-wrap">
      <Canvas
        camera={{ position: [0, 0, 8.5], fov: 52 }}
        dpr={[1, 1.5]}
        gl={{ antialias: true, alpha: true }}
      >
        <Suspense fallback={null}>
          {/* Lighting */}
          <ambientLight intensity={0.28} />
          <directionalLight position={[5, 5, 5]} intensity={0.7} color="#ffffff" />
          <pointLight position={[4, 2.5, 3]} intensity={1.5} color="#f59e0b" distance={15} />
          <pointLight position={[-4, -2, 3]} intensity={0.4} color="#3b82f6" distance={10} />

          {/* Stars Background */}
          <Stars radius={80} depth={50} count={2000} factor={4} saturation={0} fade speed={0.5} />

          {/* Architectural 3D shapes positioned to the right */}
          <ArchFrame position={[2.5, 0.2, 0]} scale={1.35} color="#f59e0b" />
          <ArchFrame position={[4.6, -0.7, -1]} scale={0.9} color="#94a3b8" />
          <ArchFrame position={[1.4, 1.8, -2]} scale={0.7} color="#fbbf24" />

          <TorusRing position={[2.6, 0, 0]} scale={2.1} speed={1} />
          <TorusRing position={[0.9, 1.3, -2]} scale={1.2} speed={0.7} />
          <TorusRing position={[4.2, -1.5, -1]} scale={1.0} speed={1.3} />

          <GridFloor />

          <OrbitControls
            enableZoom={false}
            enablePan={false}
            target={[2.2, 0, 0]}
            maxPolarAngle={Math.PI / 2 + 0.3}
            minPolarAngle={Math.PI / 2 - 0.5}
            autoRotate
            autoRotateSpeed={0.4}
          />
        </Suspense>
      </Canvas>
    </div>
  );
}
