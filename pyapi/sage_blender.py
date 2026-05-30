import bpy
import json
import sys
import os
import math

def clean_scene():
    if bpy.context.object:
        bpy.ops.object.mode_set(mode='OBJECT')
    bpy.ops.object.select_all(action='SELECT')
    bpy.ops.object.delete()
    for block in (bpy.data.meshes, bpy.data.materials, bpy.data.textures, bpy.data.images, bpy.data.cameras, bpy.data.lights):
        for b in block:
            block.remove(b)

def setup_render_engine(output_path, fps=30):
    scene = bpy.context.scene
    scene.render.engine = 'CYCLES' # Use CYCLES for Tablet (CPU)
    scene.cycles.device = 'CPU'
    scene.cycles.samples = 32 # Keep low for tablet speed
    scene.cycles.use_denoising = True
    
    scene.render.filepath = output_path
    scene.render.image_settings.file_format = 'FFMPEG'
    scene.render.ffmpeg.format = 'MPEG4'
    scene.render.ffmpeg.codec = 'H264'
    scene.render.resolution_x = 1920
    scene.render.resolution_y = 1080
    scene.render.fps = fps

def create_material(name, image_path, is_emissive=False, transparent=False):
    mat = bpy.data.materials.new(name=name)
    mat.use_nodes = True
    nodes = mat.node_tree.nodes
    links = mat.node_tree.links
    nodes.clear()

    tex_node = nodes.new('ShaderNodeTexImage')
    try:
        tex_node.image = bpy.data.images.load(image_path)
    except:
        print(f"WARNING: Could not load image {image_path}")
        return None

    bsdf = nodes.new('ShaderNodeBsdfPrincipled')
    output = nodes.new('ShaderNodeOutputMaterial')
    
    tex_node.location = (-400, 0)
    bsdf.location = (0, 0)
    output.location = (400, 0)

    links.new(tex_node.outputs['Color'], bsdf.inputs['Base Color'])
    
    if transparent:
        mat.blend_method = 'BLEND'
        mat.shadow_method = 'CLIP'
        links.new(tex_node.outputs['Alpha'], bsdf.inputs['Alpha'])
    
    if is_emissive:
        links.new(tex_node.outputs['Color'], bsdf.inputs['Emission Color'])
        bsdf.inputs['Emission Strength'].default_value = 1.0

    links.new(bsdf.outputs['BSDF'], output.inputs['Surface'])
    return mat

def build_scene(setup_data, assets_dir):
    objects_map = {}
    
    # Camera
    bpy.ops.object.camera_add(location=(0, -15, 20), rotation=(math.radians(60), 0, 0))
    cam = bpy.context.object
    cam.name = "MainCamera"
    bpy.context.scene.camera = cam
    
    # Light
    bpy.ops.object.light_add(type='SUN', location=(0, -50, 100))
    bpy.context.object.data.energy = 2.0
    
    CYL_RADIUS = 40
    
    for layer in setup_data.get('layers', []):
        # Determine filename (it was flattened during upload)
        raw_filename = layer.get('frame_filename') or layer.get('mesh_filename') or "placeholder.png"
        filename = os.path.basename(raw_filename) 
        file_path = os.path.join(assets_dir, filename)
        
        if not os.path.exists(file_path) and layer['role'] != 'background':
             print(f"Asset missing: {file_path}")
             continue

        role = layer['role']
        config = layer.get('config', {})
        z_index = layer.get('z_index', 0)
        obj = None

        if role == 'background':
            # Create Cylinder
            bpy.ops.mesh.primitive_cylinder_add(radius=CYL_RADIUS, depth=160, vertices=64)
            obj = bpy.context.object
            obj.rotation_euler = (0, math.radians(90), 0)
            if os.path.exists(file_path):
                mat = create_material(f"Mat_{layer['id']}", file_path, is_emissive=True)
                if mat: obj.data.materials.append(mat)
                # UV Project
                bpy.ops.object.mode_set(mode='EDIT')
                bpy.ops.uv.cylinder_project(direction='ALIGN_TO_OBJECT', scale_to_bounds=True)
                bpy.ops.object.mode_set(mode='OBJECT')

        elif role == 'model3d' and filename.endswith('.glb'):
            bpy.ops.import_scene.gltf(filepath=file_path)
            imported = bpy.context.selected_objects
            bpy.ops.object.empty_add(type='PLAIN_AXES')
            container = bpy.context.object
            for child in imported:
                child.parent = container
            obj = container
            
            base_h = CYL_RADIUS + 3 + (z_index * 0.2)
            obj.location = (0, 0, base_h)
            scale = config.get('scaleFactor', 1.0)
            obj.scale = (scale, scale, scale)
            obj.rotation_euler = (math.radians(90), 0, math.radians(180))

        elif role == 'plane':
            bpy.ops.mesh.primitive_plane_add(size=1)
            obj = bpy.context.object
            mat = create_material(f"Mat_{layer['id']}", file_path, transparent=True)
            if mat: obj.data.materials.append(mat)
            
            base_h = CYL_RADIUS + 3 + (z_index * 0.2)
            obj.location = (0, 0, base_h)
            scale = config.get('scaleFactor', 1.0) * 5.0
            obj.scale = (scale, scale, scale)
            obj.rotation_euler = (math.radians(90), 0, math.radians(180))
            
        if obj: objects_map[str(layer['id'])] = obj

    return objects_map

def animate(objects_map, flight_data, fps=30):
    if not flight_data: return 60
    
    last_frame = 0
    for point in flight_data:
        frame = int(point.get('time', 0) * fps)
        last_frame = max(last_frame, frame)
        
        for layer_id, trans in point.get('layers', {}).items():
            if layer_id in objects_map:
                obj = objects_map[layer_id]
                if 'x' in trans:
                    obj.location.x = trans['x']
                    obj.keyframe_insert(data_path="location", index=0, frame=frame)
                if 'rotZ' in trans:
                    # Map ThreeJS Z-rot to Blender Y-rot (Bank)
                    obj.rotation_euler.y = trans['rotZ']
                    obj.keyframe_insert(data_path="rotation_euler", index=1, frame=frame)
    return last_frame

def main():
    # blender -b -P script.py -- <job_dir>
    args = sys.argv[sys.argv.index("--") + 1:]
    job_dir = args[0]
    
    json_path = os.path.join(job_dir, "job.json")
    assets_dir = os.path.join(job_dir, "assets")
    output_dir = os.path.join(job_dir, "output")
    
    if not os.path.exists(output_dir): os.makedirs(output_dir)

    with open(json_path, 'r') as f:
        data = json.load(f)

    clean_scene()
    objects = build_scene(data.get('setup', {}), assets_dir)
    end_frame = animate(objects, data.get('flight_data', []))
    
    out_file = os.path.join(output_dir, f"render_{data['job_id']}_")
    setup_render_engine(out_file)
    bpy.context.scene.frame_end = end_frame
    
    print("Starting Render...")
    bpy.ops.render.render(animation=True)

if __name__ == "__main__":
    main()
